<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Attributes\FilterScope;
use Modules\Core\Casts\ScopeFilter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * The one check a scope filter node goes through before a query calls the scope it names.
 *
 * A filter may name only a local scope whose method carries {@see FilterScope}: the declared scopes of a model
 * class are read once by reflection, traits included, and kept per class. Parameters are checked against the
 * method signature and coerced; a scope then runs inside its own nested where and is refused when it left
 * anything on the query but where conditions. Global scopes are never touched: only the named local scope is
 * called. Placeholders (`@now`, `@user.<attribute>`) are resolved by the caller before it gets here.
 *
 * @phpstan-type ScopeParameter array{name: string, type: 'string'|'int'|'float'|'bool'|'date', nullable: bool, optional: bool, default: mixed}
 * @phpstan-type ScopeDeclaration array{name: string, client: bool, parameters: list<ScopeParameter>, unsupported: string|null}
 */
final class ScopeGuard
{
    private const array SCALAR_TYPES = ['string', 'int', 'float', 'bool'];

    /**
     * A calendar date, optionally with a time and a UTC designator or offset.
     */
    private const string ISO_8601 = '/^(\d{4})-(\d{2})-(\d{2})(?:[T ](?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/';

    /**
     * The parts of a query a filter scope must leave as it found them.
     */
    private const array QUERY_COMPONENTS = [
        'aggregate', 'columns', 'distinct', 'from', 'indexHint', 'joins', 'groups', 'havings', 'orders', 'limit',
        'groupLimit', 'offset', 'unions', 'unionLimit', 'unionOffset', 'unionOrders', 'lock',
    ];

    /**
     * @var array<class-string<Model>, array<string, ScopeDeclaration>>
     */
    private static array $declarations = [];

    /**
     * The filter scopes a model class declares, keyed by the scope name a filter uses. Class-level data only, read
     * once per class.
     *
     * @param  class-string<Model>  $model_class
     * @return array<string, ScopeDeclaration>
     */
    public static function declarations(string $model_class): array
    {
        return self::$declarations[$model_class] ??= self::readDeclarations($model_class);
    }

    /**
     * Checks a scope node against the model it is applied to and returns its parameters coerced to the declared
     * types. The message names the scope and the model, never a parameter value.
     *
     * @param  bool  $client  Whether the node comes from a CRUD request rather than an ACL
     *
     * @throws InvalidArgumentException when the scope is not declared, is ACL-only in a client request, cannot be
     *                                  declared for its signature, or the parameters do not fit
     *
     * @return list<mixed>
     */
    public static function assertFilterable(Model $model, ScopeFilter $filter, bool $client = false): array
    {
        $model_class = $model::class;
        $declaration = self::declarations($model_class)[$filter->scope] ?? null;

        if ($declaration === null) {
            throw new InvalidArgumentException(sprintf('"%s" is not a filter scope of %s.', $filter->scope, $model_class));
        }

        if ($declaration['unsupported'] !== null) {
            throw new InvalidArgumentException(sprintf('Scope "%s" of %s cannot be used as a filter: %s.', $filter->scope, $model_class, $declaration['unsupported']));
        }

        if ($client && ! $declaration['client']) {
            throw new InvalidArgumentException(sprintf('Scope "%s" of %s can be used only in ACLs.', $filter->scope, $model_class));
        }

        $declared = $declaration['parameters'];
        $required = count(array_filter($declared, static fn (array $parameter): bool => ! $parameter['optional']));
        $given = count($filter->parameters);

        if ($given < $required) {
            throw new InvalidArgumentException(sprintf('Scope "%s" of %s needs at least %d parameter(s), %d given.', $filter->scope, $model_class, $required, $given));
        }

        if ($given > count($declared)) {
            throw new InvalidArgumentException(sprintf('Scope "%s" of %s takes at most %d parameter(s), %d given.', $filter->scope, $model_class, count($declared), $given));
        }

        $coerced = [];

        foreach ($filter->parameters as $position => $value) {
            $parameter = $declared[$position];
            $subject = sprintf('Parameter $%s of scope "%s" on %s', $parameter['name'], $filter->scope, $model_class);
            $coerced[] = self::coerce($value, $parameter, $subject);
        }

        return $coerced;
    }

    /**
     * Runs the scope a node names inside its own nested where, joined to the query with `$boolean`, so its
     * conditions stay in their group in AND and OR groups alike.
     *
     * @param  Builder<Model>  $query
     * @param  string  $boolean  How the group joins the query: 'and' or 'or'
     * @param  bool  $client  Whether the node comes from a CRUD request rather than an ACL
     *
     * @throws InvalidArgumentException when the node is not filterable ({@see assertFilterable()}) or the scope did
     *                                  more than add where conditions; the query is then left untouched
     */
    public static function apply(Builder $query, ScopeFilter $filter, string $boolean = 'and', bool $client = false): void
    {
        if ($boolean !== 'and' && $boolean !== 'or') {
            throw new InvalidArgumentException(sprintf('A scope filter joins its group with "and" or "or", "%s" given.', $boolean));
        }

        $model = $query->getModel();
        $parameters = self::assertFilterable($model, $filter, $client);

        $nested = $model->newQueryWithoutRelationships();
        $before = self::shapeOf($nested);
        // Called on the model rather than through Builder::scopes(), which would wrap an OR scope in a second group.
        $returned = $model->callNamedScope($filter->scope, [$nested, ...$parameters]);

        if (($returned !== null && $returned !== $nested) || $before !== self::shapeOf($nested)) {
            throw new InvalidArgumentException(sprintf('Scope "%s" of %s does more than add where conditions, so it cannot be used as a filter.', $filter->scope, $model::class));
        }

        $query->getQuery()->addNestedWhereQuery($nested->getQuery(), $boolean);
    }

    /**
     * @param  class-string<Model>  $model_class
     * @return array<string, ScopeDeclaration>
     */
    private static function readDeclarations(string $model_class): array
    {
        $class = new ReflectionClass($model_class);
        $declarations = [];

        foreach ($class->getMethods() as $method) {
            $attributes = $method->getAttributes(FilterScope::class);

            if ($attributes === [] || $method->isPrivate()) {
                continue;
            }

            $name = self::scopeNameOf($class, $method);

            if ($name === null) {
                continue;
            }

            $declarations[$name] = self::declare($name, $method, $attributes[0]->newInstance());
        }

        return $declarations;
    }

    /**
     * The name Eloquent calls the method by, or null when Eloquent would not call it for that name: a method that
     * is neither a `#[Scope]` method nor a legacy `scopeXxx` one, or a legacy method shadowed by a `#[Scope]`
     * method of the same name, which `Model::callNamedScope()` prefers.
     *
     * @param  ReflectionClass<Model>  $class
     */
    private static function scopeNameOf(ReflectionClass $class, ReflectionMethod $method): ?string
    {
        $method_name = $method->getName();

        if ($method->getAttributes(Scope::class) !== []) {
            return $method_name;
        }

        if (mb_strlen($method_name) <= 5 || ! str_starts_with($method_name, 'scope')) {
            return null;
        }

        $name = lcfirst(mb_substr($method_name, 5));

        if ($class->hasMethod($name)) {
            $shadow = $class->getMethod($name);

            if (! $shadow->isPrivate() && $shadow->getAttributes(Scope::class) !== []) {
                return null;
            }
        }

        return $name;
    }

    /**
     * @return ScopeDeclaration
     */
    private static function declare(string $name, ReflectionMethod $method, FilterScope $attribute): array
    {
        $parameters = $method->getParameters();
        $unsupported = array_shift($parameters) === null ? 'it takes no query builder' : null;
        $declared = [];

        foreach ($parameters as $parameter) {
            $spec = self::parameterOf($parameter);

            if (is_string($spec)) {
                $unsupported ??= $spec;

                continue;
            }

            $declared[] = $spec;
        }

        return [
            'name' => $name,
            'client' => $attribute->client,
            'parameters' => $declared,
            'unsupported' => $unsupported,
        ];
    }

    /**
     * The declared shape of a scope parameter, or the reason a filter cannot carry it.
     *
     * @return ScopeParameter|string
     */
    private static function parameterOf(ReflectionParameter $parameter): array|string
    {
        $name = $parameter->getName();

        if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
            return sprintf('parameter $%s is variadic or by reference', $name);
        }

        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return sprintf('parameter $%s has no single declared type', $name);
        }

        $type_name = $type->getName();
        $kind = match (true) {
            in_array($type_name, self::SCALAR_TYPES, true) => $type_name,
            ! $type->isBuiltin() && is_a(Carbon::class, $type_name, true) => 'date',
            default => null,
        };

        if ($kind === null) {
            return sprintf('parameter $%s has type %s, which a filter cannot carry', $name, $type_name);
        }

        $optional = $parameter->isOptional();

        return [
            'name' => $name,
            'type' => $kind,
            'nullable' => $type->allowsNull(),
            'optional' => $optional,
            'default' => $optional && $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
        ];
    }

    /**
     * @param  ScopeParameter  $parameter
     *
     * @throws InvalidArgumentException
     */
    private static function coerce(mixed $value, array $parameter, string $subject): mixed
    {
        if ($value === null) {
            if ($parameter['nullable']) {
                return null;
            }

            throw new InvalidArgumentException(sprintf('%s must not be null.', $subject));
        }

        if ($parameter['type'] === 'date' && $value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException(sprintf('%s: arrays and objects are not accepted.', $subject));
        }

        if ($parameter['type'] === 'date') {
            return self::toDate($value, $subject);
        }

        $coerced = match ($parameter['type']) {
            'int' => self::toInt($value),
            'float' => self::toFloat($value),
            'bool' => is_bool($bool = BooleanInput::coerce($value)) ? $bool : null,
            default => is_string($value) ? $value : null,
        };

        if ($coerced === null) {
            throw new InvalidArgumentException(sprintf('%s must be %s %s.', $subject, $parameter['type'] === 'int' ? 'an' : 'a', $parameter['type']));
        }

        return $coerced;
    }

    private static function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^-?\d+$/', $value) !== 1) {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($int) ? $int : null;
    }

    private static function toFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $float = filter_var($value, FILTER_VALIDATE_FLOAT);

        return is_float($float) ? $float : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function toDate(mixed $value, string $subject): Carbon
    {
        $invalid = new InvalidArgumentException(sprintf('%s must be an ISO 8601 date.', $subject));

        if (! is_string($value) || preg_match(self::ISO_8601, $value, $parts) !== 1 || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw $invalid;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw $invalid;
        }
    }

    /**
     * Everything a scope could change on a query besides its where conditions.
     *
     * @param  Builder<Model>  $builder
     * @return array<string, mixed>
     */
    private static function shapeOf(Builder $builder): array
    {
        $base = $builder->getQuery();
        $shape = ['eager_loads' => $builder->getEagerLoads()];

        foreach (self::QUERY_COMPONENTS as $component) {
            $shape[$component] = $base->{$component};
        }

        $bindings = $base->getRawBindings();
        unset($bindings['where']);
        $shape['bindings'] = $bindings;

        return $shape;
    }
}
