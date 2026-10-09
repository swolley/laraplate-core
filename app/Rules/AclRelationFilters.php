<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use function models;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FiltersGroupCast;
use Modules\Core\Casts\RelationFilter;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Support\PermissionName;
use Override;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Checks, when an ACL is saved, that every relation filter points at something that exists: the relation
 * is a relation method of the entity the ACL's permission is about, a morph relation lists morph types
 * and a plain relation does not, and every morph type is a model. Nested relation filters are checked
 * against the related entity, and against each accepted morph type.
 *
 * The shape of the filters is {@see QueryBuilder}'s concern; this rule only looks at what the shape
 * names, and only when the filters hold a relation filter at all.
 */
final class AclRelationFilters implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $filters = $this->toGroup($value);

        if (! $filters instanceof FiltersGroup || ! $this->holdsRelationFilter($filters)) {
            return;
        }

        $model = $this->entityOfPermission();

        if (! $model instanceof Model) {
            $fail($attribute . ' holds a relation filter but the entity of the permission cannot be resolved');

            return;
        }

        $this->validateGroup($attribute, $filters, $model, $fail);
    }

    private function toGroup(mixed $value): ?FiltersGroup
    {
        if ($value instanceof FiltersGroup) {
            return $value;
        }

        try {
            return new FiltersGroupCast()->get(new ACL, 'filters', $value, []);
        } catch (Throwable) {
            return null;
        }
    }

    private function holdsRelationFilter(FiltersGroup $filters): bool
    {
        foreach ($filters->filters as $node) {
            if ($node instanceof RelationFilter) {
                return true;
            }

            if ($node instanceof FiltersGroup && $this->holdsRelationFilter($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The entity the ACL restricts: the model whose permission names match the ACL's permission.
     */
    private function entityOfPermission(): ?Model
    {
        $permission_id = $this->data['permission_id'] ?? null;

        if (! is_int($permission_id) && ! is_string($permission_id)) {
            return null;
        }

        $name = Permission::query()->whereKey($permission_id)->value('name');

        if (! is_string($name)) {
            return null;
        }

        $operation = mb_substr($name, (int) mb_strrpos($name, '.') + 1);

        foreach (models() as $class) {
            if ($name === PermissionName::forClass($class, $operation)) {
                return $this->instance($class);
            }
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function instance(string $class): Model
    {
        return new ReflectionClass($class)->newInstanceWithoutConstructor();
    }

    private function validateGroup(string $attribute, FiltersGroup $filters, Model $model, Closure $fail): void
    {
        foreach ($filters->filters as $node) {
            if ($node instanceof FiltersGroup) {
                $this->validateGroup($attribute, $node, $model, $fail);
            } elseif ($node instanceof RelationFilter) {
                $this->validateRelation($attribute, $node, $model, $fail);
            }
        }
    }

    private function validateRelation(string $attribute, RelationFilter $filter, Model $model, Closure $fail): void
    {
        $relation = $this->relationOf($model, $filter->relation);

        if (! $relation instanceof Relation) {
            $fail(sprintf('%s relation "%s" is not a relation of %s', $attribute, $filter->relation, $model::class));

            return;
        }

        if (! $relation instanceof MorphTo) {
            if ($filter->morph_types !== null) {
                $fail(sprintf('%s relation "%s" is not a morph relation, "morph_types" must not be given', $attribute, $filter->relation));

                return;
            }

            $this->validateGroup($attribute, $filter->filters, $relation->getRelated(), $fail);

            return;
        }

        if ($filter->morph_types === null) {
            $fail(sprintf('%s relation "%s" is a morph relation, "morph_types" is required', $attribute, $filter->relation));

            return;
        }

        foreach ($filter->morph_types as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                $fail(sprintf('%s morph type "%s" is not a model', $attribute, $type));

                continue;
            }

            $this->validateGroup($attribute, $filter->filters, $this->instance($class), $fail);
        }
    }

    /**
     * The relation behind a public method of the model whose declared return type is a relation. The
     * method is called only after that check, so a filter cannot invoke an arbitrary method by name.
     *
     * @return Relation<Model, Model, mixed>|null
     */
    private function relationOf(Model $model, string $name): ?Relation
    {
        if (! method_exists($model, $name)) {
            return null;
        }

        $method = new ReflectionMethod($model, $name);
        $type = $method->getReturnType();

        if (! $method->isPublic() || $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        if (! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
            return null;
        }

        try {
            $relation = $model->{$name}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof Relation ? $relation : null;
    }
}
