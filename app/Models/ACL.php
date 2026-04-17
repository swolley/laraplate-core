<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FiltersGroupCast;
use Modules\Core\Overrides\Model;
use Modules\Core\Rules\QueryBuilder;
use Override;

/**
 * ACL (Access Control List) model for row-level security.
 *
 * ACLs define filters that restrict which records a user can access
 * when they have a specific permission. The system uses inheritance:
 *
 * - If a role has an ACL for a permission → use it (overrides parent)
 * - If a role has NO ACL → inherit from parent role
 * - If unrestricted=true → no filters applied (full access)
 * - Multiple non-hierarchical roles → combine with OR (union)
 *
 * @property int $id
 * @property int $permission_id
 * @property FiltersGroup|null $filters
 * @property array<string, mixed>|null $sort
 * @property string|null $description
 * @property bool $unrestricted
 * @property int $priority
 * @property bool $is_active
 * @mixin IdeHelperACL
 */
final class ACL extends Model
{
    #[Override]
    protected $table = 'acls';

    #[Override]
    protected $fillable = [
        'permission_id',
        'filters',       // Stored as JSON - query builder filters
        'sort',          // Optional: stored as JSON
        'description',   // Optional: human readable description
        'unrestricted',  // If true, no filters applied (full access)
        'priority',      // Higher priority ACLs evaluated first
        'is_active',     // If false, ACL is ignored (DB column name)
    ];

    /**
     * The permission that belongs to the ACL.
     *
     * @return BelongsTo<Permission>
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'permission_id' => ['required', 'exists:permissions,id'],
            'filters' => [new QueryBuilder()],
            'sort.*.property' => ['string'],
            'sort.*.direction' => ['in:asc,desc,ASC,DESC'],
            'description' => ['string', 'max:255', 'nullable'],
            'unrestricted' => ['boolean'],
            'priority' => ['integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ]);

        return $rules;
    }

    /**
     * Check if this ACL grants unrestricted access.
     */
    public function isUnrestricted(): bool
    {
        return $this->unrestricted === true;
    }

    /**
     * Check if this ACL has any filters defined.
     */
    public function hasFilters(): bool
    {
        return $this->filters !== null && $this->filters->filters !== [];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forPermission(Builder $query, int $permission_id): Builder
    {
        return $query->where('permission_id', $permission_id);
    }

    /**
     * Scope to get only enabled ACLs.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority (highest first).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function byPriority(Builder $query): Builder
    {
        return $query->orderByDesc('priority');
    }

    protected function casts(): array
    {
        return [
            'filters' => FiltersGroupCast::class,
            'sort' => 'array',
            'unrestricted' => 'boolean',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
