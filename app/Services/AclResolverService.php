<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

/**
 * Service for resolving effective ACLs for users based on their roles and permissions.
 *
 * The ACL resolution follows these rules:
 *
 * 1. PERMISSION CHECK: User must have the permission (via role or direct assignment)
 * 2. INHERITANCE: If a role has an ACL for the permission → use it (overrides parent)
 *                 If a role has NO ACL → inherit from parent role in hierarchy
 * 3. UNRESTRICTED: ACLs with unrestricted=true are "transparent" - they don't contribute
 *                  filters to the query. The role still exists but imposes no restrictions.
 * 4. MULTIPLE ROLES: Non-hierarchical roles that contribute filters are combined with OR
 * 5. NO CONTRIBUTORS: If no ACLs contribute filters → user sees everything
 * 6. PRIORITY: Higher priority ACLs are evaluated first within the same level
 *
 * Example scenarios:
 * ```
 * Scenario 1: Inheritance with override
 * guest (root) → ACL: { filters: [status = 'published'] }
 *   └── editor → NO ACL → inherits from guest (sees only published)
 *       └── admin → ACL: { unrestricted: true } → transparent, no filters
 *
 * Scenario 2: Multiple roles
 * User with roles: sales_it (country='IT') + sales_de (country='DE')
 * Result: country='IT' OR country='DE'
 *
 * Scenario 3: Mixed roles
 * User with roles: sales (country='IT') + supervisor (unrestricted=true)
 * Result: country='IT' (supervisor doesn't contribute)
 * ```
 */
final class AclResolverService
{
    private const CACHE_PREFIX = 'acl:resolved:';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the effective ACLs for a user on a specific permission.
     *
     * @return Collection<int, ACL> Collection of effective ACLs (empty if unrestricted or no ACLs)
     */
    public function getEffectiveAcls(User $user, Permission $permission): Collection
    {
        $cache_key = self::CACHE_PREFIX . "user:{$user->id}:perm:{$permission->id}";

        return Cache::remember($cache_key, self::CACHE_TTL, function () use ($user, $permission): Collection {
            return $this->resolveAcls($user, $permission);
        });
    }

    /**
     * Get combined filters for a user on a specific permission.
     * Returns null if no filters apply (user sees everything).
     *
     * Logic:
     * - ACLs with unrestricted=true are "transparent" - they don't contribute to the query
     * - ACLs with filters contribute those filters
     * - Multiple contributing ACLs are combined with OR (union of access)
     * - If NO ACLs contribute filters → user sees everything
     */
    public function getCombinedFilters(User $user, Permission $permission): ?FiltersGroup
    {
        $acls = $this->getEffectiveAcls($user, $permission);

        if ($acls->isEmpty()) {
            return null;
        }

        // Filter out unrestricted ACLs - they don't contribute to the query
        // Only ACLs with actual filters contribute
        $contributing_acls = $acls->filter(
            fn (ACL $acl) => ! $acl->isUnrestricted() && $acl->hasFilters(),
        );

        // If no ACLs contribute filters, user sees everything
        if ($contributing_acls->isEmpty()) {
            return null;
        }

        // Collect all filter groups from contributing ACLs
        $all_filters = $contributing_acls
            ->map(fn (ACL $acl) => $acl->filters)
            ->values()
            ->all();

        // If only one filter group, return it directly
        if (count($all_filters) === 1) {
            return $all_filters[0];
        }

        // Combine multiple filter groups with OR (union of access from different roles)
        return new FiltersGroup(
            filters: $all_filters,
            operator: WhereClause::OR,
        );
    }

    /**
     * Check if a user has unrestricted access to a permission.
     *
     * A user has unrestricted access when NO ACLs contribute filters.
     * This happens when:
     * - User has no ACLs at all
     * - All ACLs are marked as unrestricted=true
     * - No ACLs have actual filters defined
     */
    public function hasUnrestrictedAccess(User $user, Permission $permission): bool
    {
        $acls = $this->getEffectiveAcls($user, $permission);

        if ($acls->isEmpty()) {
            return true;
        }

        // User has unrestricted access if no ACL contributes filters
        $has_contributing_acls = $acls->contains(
            fn (ACL $acl) => ! $acl->isUnrestricted() && $acl->hasFilters(),
        );

        return ! $has_contributing_acls;
    }

    /**
     * Clear cached ACLs for a user.
     */
    public function clearCacheForUser(User $user): void
    {
        // Get all permissions and clear cache for each
        $permissions = Permission::all(['id']);

        foreach ($permissions as $permission) {
            Cache::forget(self::CACHE_PREFIX . "user:{$user->id}:perm:{$permission->id}");
        }
    }

    /**
     * Clear cached ACLs for a permission.
     */
    public function clearCacheForPermission(Permission $permission): void
    {
        // This is more expensive - need to clear for all users
        // In production, consider using cache tags
        Cache::flush(); // TODO: Use cache tags for more granular invalidation
    }

    /**
     * Resolve ACLs for a user on a permission.
     *
     * @return Collection<int, ACL>
     */
    private function resolveAcls(User $user, Permission $permission): Collection
    {
        // SuperAdmin bypasses all ACLs
        if ($user->isSuperAdmin()) {
            return collect([
                $this->createUnrestrictedAcl($permission),
            ]);
        }

        // Get user's roles (including inherited permissions from parent roles)
        $roles = $user->roles;

        if ($roles->isEmpty()) {
            return collect();
        }

        $resolved_acls = collect();

        foreach ($roles as $role) {
            $acl = $this->resolveAclForRole($role, $permission);

            if ($acl !== null) {
                $resolved_acls->push($acl);
            }
        }

        return $resolved_acls;
    }

    /**
     * Resolve ACL for a specific role on a permission.
     * Implements inheritance: if role has no ACL, check parent roles.
     */
    private function resolveAclForRole(Role $role, Permission $permission): ?ACL
    {
        // First check if role even has this permission
        if (! $role->hasPermission($permission->name)) {
            return null;
        }

        // Look for ACL on this role's permission
        $acl = $this->findAclForRolePermission($role, $permission);

        if ($acl !== null) {
            return $acl;
        }

        // No ACL found for this role, check parent roles (inheritance)
        return $this->resolveAclFromAncestors($role, $permission);
    }

    /**
     * Find ACL directly associated with a role's permission.
     */
    private function findAclForRolePermission(Role $role, Permission $permission): ?ACL
    {
        // Check if there's an ACL for this permission
        // The ACL is linked to permission, and permission is linked to role
        return ACL::query()
            ->active()
            ->forPermission($permission->id)
            ->byPriority()
            ->whereHas('permission.roles', function (Builder $query) use ($role): void {
                $query->where('roles.id', $role->id);
            })
            ->first();
    }

    /**
     * Resolve ACL from ancestor roles (parent hierarchy).
     */
    private function resolveAclFromAncestors(Role $role, Permission $permission): ?ACL
    {
        // Get ancestors ordered from closest to root
        $ancestors = $role->ancestors;

        foreach ($ancestors as $ancestor) {
            $acl = $this->findAclForRolePermission($ancestor, $permission);

            if ($acl !== null) {
                return $acl;
            }
        }

        return null;
    }

    /**
     * Create a virtual unrestricted ACL for superadmin.
     */
    private function createUnrestrictedAcl(Permission $permission): ACL
    {
        $acl = new ACL();
        $acl->permission_id = $permission->id;
        $acl->unrestricted = true;
        $acl->enabled = true;
        $acl->priority = PHP_INT_MAX;

        return $acl;
    }
}
