<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FiltersGroupCast;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Exceptions\ScopeFilterException;
use Modules\Core\Models\ACL;
use Modules\Core\Support\AclScopeCheck;
use Modules\Core\Support\PermissionName;
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

        if (! $filters instanceof FiltersGroup || ! AclScopeCheck::holdsScopeFilter($filters)) {
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
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    private function validateGroup(string $attribute, FiltersGroup $filters, Model $model, Closure $fail): void
    {
        AclScopeCheck::walk(
            $filters,
            $model,
            static function (ScopeFilter $filter, ?Model $target) use ($attribute, $fail): void {
                if (! $target instanceof Model) {
                    return;
                }

                try {
                    ScopeGuard::assertStorable($target, $filter);
                } catch (ScopeFilterException $exception) {
                    $fail($attribute . ' ' . $exception->getMessage());
                }
            },
            static function () use ($attribute, $fail): void {
                $fail(sprintf('%s holds a filter node of an unknown kind', $attribute));
            },
        );
    }
}
