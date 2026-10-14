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
use Modules\Core\Exceptions\ScopeFilterException;
use Modules\Core\Models\ACL;
use Modules\Core\Support\PermissionName;
use Modules\Core\Support\RelationGuard;
use Modules\Core\Support\ScopeGuard;
use Override;

/**
 * Checks, when an ACL is saved, that every scope node names a scope the filtered model can run as a filter: declared
 * with {@see \Modules\Core\Attributes\FilterScope}, with parameters that fit (placeholders allowed where their
 * type fits) and adding nothing but where conditions ({@see ScopeGuard::assertStorable()}). A scope node at any
 * depth is checked against the entity the ACL's permission is about, and inside a relation filter against the
 * related entity, and against each morph type of a morph relation.
 *
 * The shape of the filters is {@see QueryBuilder}'s concern and the relations themselves are
 * {@see AclRelationFilters}'s: a relation or a morph type that does not resolve is reported there and skipped here.
 * A node of an unknown kind is refused, never skipped.
 */
final class AclScopeFilters implements DataAwareRule, ValidationRule
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
        } catch (InvalidArgumentException) {
            // Unreadable filters are reported by the shape and relation rules.
            return;
        }

        if (! $filters instanceof FiltersGroup || ! $this->holdsScopeFilter($filters)) {
            return;
        }

        $permission_id = $this->data['permission_id'] ?? null;
        $model = is_int($permission_id) || is_string($permission_id) ? PermissionName::modelOfPermission($permission_id) : null;

        if (! $model instanceof Model) {
            $fail($attribute . ' holds a scope filter but the entity of the permission cannot be resolved');

            return;
        }

        $this->validateGroup($attribute, $filters, $model, $fail);
    }

    /**
     * Whether the filters need the walk: they hold a scope node at any depth, or a node of an unknown kind to refuse.
     */
    private function holdsScopeFilter(FiltersGroup $filters): bool
    {
        foreach ($filters->filters as $node) {
            $holds = match (true) {
                $node instanceof FiltersGroup => $this->holdsScopeFilter($node),
                $node instanceof RelationFilter => $this->holdsScopeFilter($node->filters),
                $node instanceof Filter => false,
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
                $node instanceof ScopeFilter => $this->validateScope($attribute, $node, $model, $fail),
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

    private function validateScope(string $attribute, ScopeFilter $filter, Model $model, Closure $fail): void
    {
        try {
            ScopeGuard::assertStorable($model, $filter);
        } catch (ScopeFilterException $exception) {
            $fail($attribute . ' ' . $exception->getMessage());
        }
    }

    private function validateRelation(string $attribute, RelationFilter $filter, Model $model, Closure $fail): void
    {
        $relation = RelationGuard::relationOf($model, $filter->relation);

        if (! $relation instanceof Relation) {
            return;
        }

        if (! $relation instanceof MorphTo) {
            $this->validateGroup($attribute, $filter->filters, $relation->getRelated(), $fail);

            return;
        }

        foreach ($filter->morph_types ?? [] as $type) {
            $morph_model = RelationGuard::morphModel($type);

            if ($morph_model instanceof Model) {
                $this->validateGroup($attribute, $filter->filters, $morph_model, $fail);
            }
        }
    }
}
