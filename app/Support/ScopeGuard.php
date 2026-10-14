<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Carbon;
use Modules\Core\Attributes\FilterScope;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Exceptions\ScopeFilterException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Throwable;

/**
 * The one check a scope filter node goes through before a query calls the scope it names.
 *
 * A filter may name only a local scope whose method carries {@see FilterScope}: the declared scopes of a model
 * class are read once by reflection, traits included, and kept per class. Parameters are checked against the
 * method signature and coerced; a scope then runs inside its own nested where and is refused when it left
 * anything on the query but where conditions. Global scopes are never touched: only the named local scope is
 * called. Placeholders (`@now`, `@user.<attribute>`) are resolved by the caller before a node is applied; an ACL
 * being saved holds them unresolved and is checked by {@see assertStorable()}.
 *
 * Every refusal is a {@see ScopeFilterException}, whose message never carries a parameter value.
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
    private const string ISO_8601 = '/^(\d{4})-(\d{2})-(\d{2})(?:[T ](?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:?\d{2})?)?\z/';

    /**
     * The parts of a query a filter scope must leave as it found them.
     */
    private const array QUERY_COMPONENTS = [
        'aggregate', 'columns', 'distinct', 'from', 'indexHint', 'joins', 'groups', 'havings', 'orders', 'limit',
        'groupLimit', 'offset', 'unions', 'unionLimit', 'unionOffset', 'unionOrders', 'lock', 'beforeQueryCallbacks',
    ];

    /**
     * The value a placeholder stands for in the save-time run, by declared type, when the parameter has no default.
     */
    private const array SAMPLES = ['string' => 'sample', 'int' => 1, 'float' => 1.0, 'bool' => true];

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
     * @throws ScopeFilterException when the scope is not declared, is ACL-only in a client request, cannot be
     *                              declared for its signature, or the parameters do not fit
     *
     * @return list<mixed>
     */
    public static function assertFilterable(Model $model, ScopeFilter $filter, bool $client = false): array
    {
        return self::checkedParameters($model, $filter, $client, false);
    }

    /**
     * Checks a scope node of an ACL as it is saved, when its parameters may still be placeholders: `@now` and
     * `@today` only in a date position, `@user.<attribute>` in any position (its value is typed when it is used).
     * The scope is then run once on a throwaway query of the model, each placeholder replaced by the parameter's
     * default or else by a sample of its type, so a scope that does more than add where conditions is refused at
     * save as it would be at use. The query is only built, never executed.
     *
     * @throws ScopeFilterException when the node would be refused at use, or the scope fails when it runs
     */
    public static function assertStorable(Model $model, ScopeFilter $filter): void
    {
        $samples = self::checkedParameters($model, $filter, false, true);

        try {
            self::apply($model::query(), new ScopeFilter($filter->scope, $samples));
        } catch (ScopeFilterException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ScopeFilterException(sprintf('Scope "%s" of %s fails when it runs with these parameters (a placeholder runs with the default or a sample value of its type), so it cannot be used as a filter.', $filter->scope, $model::class));
        }
    }

    /**
     * Checks a scope node of a client request (a CRUD request, an AI tool call) as it is parsed, before it is merged
     * with the caller's ACL: the scope must be exposed to clients, its parameters must fit, taken literally (a client
     * gets no placeholders), and it must add nothing but where conditions. The scope is run once on a throwaway query
     * of the model, which is built and never executed.
     *
     * The refusal is safe to show the client: it names the scope and the reason, with the model as "this entity",
     * and never a parameter value nor the message of an exception the scope throws itself.
     *
     * @throws ScopeFilterException when the node may not be used by a client
     */
    public static function assertClientFilterable(Model $model, ScopeFilter $filter): void
    {
        try {
            self::apply($model->newModelQuery(), $filter, 'and', true);
        } catch (ScopeFilterException $exception) {
            throw new ScopeFilterException(str_replace($model::class, 'this entity', $exception->getMessage()), previous: $exception);
        } catch (Throwable $exception) {
            throw new ScopeFilterException(sprintf('Scope "%s" fails with these parameters.', $filter->scope), previous: $exception);
        }
    }

    /**
     * Runs the scope a node names inside its own nested where, joined to the query with `$boolean`, so its
     * conditions stay in their group in AND and OR groups alike.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $boolean  How the group joins the query: 'and' or 'or'
     * @param  bool  $client  Whether the node comes from a CRUD request rather than an ACL
     *
     * @throws ScopeFilterException when the node is not filterable ({@see assertFilterable()}) or the scope did more
     *                              than add where conditions; the query is then left untouched. An exception the
     *                              scope throws itself goes through unchanged.
     */
    public static function apply(Builder $query, ScopeFilter $filter, string $boolean = 'and', bool $client = false): void
    {
        if ($boolean !== 'and' && $boolean !== 'or') {
            throw new ScopeFilterException(sprintf('A scope filter joins its group with "and" or "or", "%s" given.', $boolean));
        }

        $model = $query->getModel();
        $parameters = self::assertFilterable($model, $filter, $client);

        $nested = $model->newQueryWithoutRelationships();
        $before = self::shapeOf($nested);
        $scopes_before = self::globalScopesOf($nested);
        // Called on the model rather than through Builder::scopes(), which would wrap an OR scope in a second group.
        $returned = $model->callNamedScope($filter->scope, [$nested, ...$parameters]);

        if (($returned !== null && $returned !== $nested) || $before !== self::shapeOf($nested) || self::addsGlobalScope($scopes_before, self::globalScopesOf($nested))) {
            throw new ScopeFilterException(sprintf('Scope "%s" of %s does more than add where conditions, so it cannot be used as a filter.', $filter->scope, $model::class));
        }

        $query->getQuery()->addNestedWhereQuery($nested->getQuery(), $boolean);
    }

    /**
     * @param  bool  $placeholders  Whether a parameter may be a placeholder, replaced in the result by its sample
     *
     * @throws ScopeFilterException
     *
     * @return list<mixed>
     */
    private static function checkedParameters(Model $model, ScopeFilter $filter, bool $client, bool $placeholders): array
    {
        $model_class = $model::class;
        $declaration = self::declarations($model_class)[$filter->scope] ?? null;

        if ($declaration === null) {
            throw new ScopeFilterException(sprintf('"%s" is not a filter scope of %s.', $filter->scope, $model_class));
        }

        // The client flag first: what an ACL-only scope cannot carry is none of a client's business.
        if ($client && ! $declaration['client']) {
            throw new ScopeFilterException(sprintf('Scope "%s" of %s can be used only in ACLs.', $filter->scope, $model_class));
        }

        if ($declaration['unsupported'] !== null) {
            throw new ScopeFilterException(sprintf('Scope "%s" of %s cannot be used as a filter: %s.', $filter->scope, $model_class, $declaration['unsupported']));
        }

        $declared = $declaration['parameters'];
        $required = count(array_filter($declared, static fn (array $parameter): bool => ! $parameter['optional']));
        $given = count($filter->parameters);

        if ($given < $required) {
            throw new ScopeFilterException(sprintf('Scope "%s" of %s needs at least %d parameter(s), %d given.', $filter->scope, $model_class, $required, $given));
        }

        if ($given > count($declared)) {
            throw new ScopeFilterException(sprintf('Scope "%s" of %s takes at most %d parameter(s), %d given.', $filter->scope, $model_class, count($declared), $given));
        }

        $coerced = [];

        foreach ($filter->parameters as $position => $value) {
            $parameter = $declared[$position];
            $subject = sprintf('Parameter $%s of scope "%s" on %s', $parameter['name'], $filter->scope, $model_class);
            $coerced[] = $placeholders && FilterPlaceholders::isPlaceholder($value)
                ? self::sampleFor($value, $parameter, $subject)
                : self::coerce($value, $parameter, $subject);
        }

        return $coerced;
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
     * @throws ScopeFilterException
     */
    private static function coerce(mixed $value, array $parameter, string $subject): mixed
    {
        if ($value === null) {
            if ($parameter['nullable']) {
                return null;
            }

            throw new ScopeFilterException(sprintf('%s must not be null.', $subject));
        }

        if ($parameter['type'] === 'date' && $value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_array($value) || is_object($value)) {
            throw new ScopeFilterException(sprintf('%s: arrays and objects are not accepted.', $subject));
        }

        if ($parameter['type'] === 'date') {
            return self::toDate($value, $subject);
        }

        $coerced = match ($parameter['type']) {
            'int' => self::toInt($value),
            'float' => self::toFloat($value),
            'bool' => $value !== '' && is_bool($bool = BooleanInput::coerce($value)) ? $bool : null,
            default => is_string($value) ? $value : null,
        };

        if ($coerced === null) {
            throw new ScopeFilterException(sprintf('%s must be %s %s.', $subject, $parameter['type'] === 'int' ? 'an' : 'a', $parameter['type']));
        }

        return $coerced;
    }

    /**
     * What a placeholder parameter runs with in the save-time check: the declared default, or a sample of the
     * declared type. A date placeholder is accepted only in a date position.
     *
     * @param  ScopeParameter  $parameter
     *
     * @throws ScopeFilterException
     */
    private static function sampleFor(mixed $placeholder, array $parameter, string $subject): mixed
    {
        if (FilterPlaceholders::isDate($placeholder) && $parameter['type'] !== 'date') {
            throw new ScopeFilterException(sprintf('%s is not a date, so it cannot take @now or @today.', $subject));
        }

        if ($parameter['optional']) {
            return $parameter['default'];
        }

        return $parameter['type'] === 'date' ? Carbon::now() : self::SAMPLES[$parameter['type']];
    }

    private static function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^-?\d+\z/', $value) !== 1) {
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
     * @throws ScopeFilterException
     */
    private static function toDate(mixed $value, string $subject): Carbon
    {
        $invalid = new ScopeFilterException(sprintf('%s must be an ISO 8601 date.', $subject));

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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
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
        // Callbacks that narrow the rows once fetched: a nested where never carries them.
        $shape['after_query'] = self::protectedArray($builder, Builder::class, 'afterQueryCallbacks');
        $shape['base_after_query'] = self::protectedArray($base, BaseBuilder::class, 'afterQueryCallbacks');

        return $shape;
    }

    /**
     * The global scopes registered on a builder. Never applied to the nested builder: only its wheres are taken.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $builder
     * @return array<array-key, mixed>
     */
    private static function globalScopesOf(Builder $builder): array
    {
        return self::protectedArray($builder, Builder::class, 'scopes');
    }

    /**
     * A protected array property of a framework builder, which offers no getter for it.
     *
     * @param  class-string  $class
     * @return array<array-key, mixed>
     */
    private static function protectedArray(object $builder, string $class, string $property): array
    {
        $value = new ReflectionProperty($class, $property)->getValue($builder);

        return is_array($value) ? $value : [];
    }

    /**
     * Whether the scope registered a global scope, or replaced one, on the nested builder: its conditions would
     * never reach the filtered query, so the node would match every row. Removing one is harmless, since the
     * nested builder's global scopes are never applied and the filtered query keeps its own (the validity scopes
     * do it).
     *
     * @param  array<array-key, mixed>  $before
     * @param  array<array-key, mixed>  $after
     */
    private static function addsGlobalScope(array $before, array $after): bool
    {
        foreach ($after as $identifier => $scope) {
            if (! array_key_exists($identifier, $before) || $before[$identifier] !== $scope) {
                return true;
            }
        }

        return false;
    }
}
