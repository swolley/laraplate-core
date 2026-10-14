<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FiltersGroupCast;
use Modules\Core\Casts\RelationFilter;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Models\ACL;
use Modules\Core\Support\PermissionName;
use Modules\Core\Support\RelationGuard;
use Override;

/**
 * Checks, when an ACL is saved, that every relation filter points at something that exists: the relation
 * is a relation method of the entity the ACL's permission is about, a morph relation lists morph types
 * and a plain relation does not, and every morph type is a model. Nested relation filters are checked
 * against the related entity, and against each accepted morph type.
 *
 * The shape of the filters is {@see QueryBuilder}'s concern; this rule only looks at what the shape
 * names, and only when the filters hold a relation filter at all. A node of an unknown kind is refused, never
 * skipped.
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
        try {
            $filters = $value instanceof FiltersGroup ? $value : new FiltersGroupCast()->get(new ACL, 'filters', $value, $this->data);
        } catch (InvalidArgumentException $exception) {
            $fail($attribute . ' cannot be read as filters: ' . $exception->getMessage());

            return;
        }

        if (! $filters instanceof FiltersGroup || ! $this->holdsRelationFilter($filters)) {
            return;
        }

        $permission_id = $this->data['permission_id'] ?? null;
        $model = is_int($permission_id) || is_string($permission_id) ? PermissionName::modelOfPermission($permission_id) : null;

        if (! $model instanceof Model) {
            $fail($attribute . ' holds a relation filter but the entity of the permission cannot be resolved');

            return;
        }

        $this->validateGroup($attribute, $filters, $model, $fail);
    }

    /**
     * Whether the filters need the walk: they hold a relation filter, or a node of an unknown kind to refuse.
     */
    private function holdsRelationFilter(FiltersGroup $filters): bool
    {
        foreach ($filters->filters as $node) {
            $holds = match (true) {
                $node instanceof FiltersGroup => $this->holdsRelationFilter($node),
                $node instanceof Filter, $node instanceof ScopeFilter => false,
                default => true,
            };

            if ($holds) {
                return true;
            }
        }

        return false;
    }

    private function validateGroup(string $attribute, FiltersGroup $filters, Model $model, Closure $fail): void
    {
        foreach ($filters->filters as $node) {
            match (true) {
                $node instanceof FiltersGroup => $this->validateGroup($attribute, $node, $model, $fail),
                $node instanceof RelationFilter => $this->validateRelation($attribute, $node, $model, $fail),
                $node instanceof ScopeFilter => null,
                default => $this->refuseUnknownNode($attribute, $node, $fail),
            };
        }
    }

    /**
     * A column filter needs no check here; any other node is of a kind this rule does not know, and is refused.
     *
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function refuseUnknownNode(string $attribute, mixed $node, Closure $fail): void
    {
        if (! $node instanceof Filter) {
            $fail(sprintf('%s holds a filter node of an unknown kind', $attribute));
        }
    }

    private function validateRelation(string $attribute, RelationFilter $filter, Model $model, Closure $fail): void
    {
        $relation = RelationGuard::relationOf($model, $filter->relation);

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
            $morph_model = RelationGuard::morphModel($type);

            if (! $morph_model instanceof Model) {
                $fail(sprintf('%s morph type "%s" is not a model', $attribute, $type));

                continue;
            }

            $this->validateGroup($attribute, $filter->filters, $morph_model, $fail);
        }
    }
}
