<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use FilesystemIterator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

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
    private const int CACHE_TTL = 3600; // 1 hour

    /**
     * Get the effective ACLs for a user on a specific permission.
     *
     * @return Collection<int, ACL> Collection of effective ACLs (empty if unrestricted or no ACLs)
     */
    public function getEffectiveAcls(User $user, Permission $permission): Collection
    {
        $cache_key = $this->buildCacheKey($user->id, $permission->id);

        return Cache::remember($cache_key, self::CACHE_TTL, fn (): Collection => $this->resolveAcls($user, $permission));
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
            fn (ACL $acl): bool => ! $acl->isUnrestricted() && $acl->hasFilters(),
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
            operator: WhereClause::Or,
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
            fn (ACL $acl): bool => ! $acl->isUnrestricted() && $acl->hasFilters(),
        );

        return ! $has_contributing_acls;
    }

    /**
     * Clear cached ACLs for a user.
     */
    public function clearCacheForUser(User $user): void
    {
        // Get all permissions and clear cache for each
        $permissions = Permission::query()->select('id')->get();

        foreach ($permissions as $permission) {
            Cache::forget($this->buildCacheKey($user->id, $permission->id));
        }
    }

    /**
     * Clear cached ACLs for a specific permission using targeted key-based invalidation.
     *
     * When the number of affected users exceeds the configured threshold
     * (config('core.acl.clear_threshold', 500)), falls back to flushing all
     * keys with the 'acl:' prefix to avoid excessive individual deletes.
     */
    public function clearCacheForPermission(Permission $permission): void
    {
        $threshold = (int) config('core.acl.clear_threshold', 500);

        // Collect all user IDs that have this permission (directly or via roles)
        $user_ids = $this->getUserIdsForPermission($permission);

        if ($threshold < $user_ids->count()) {
            $this->flushAclPrefixedKeys();

            return;
        }

        foreach ($user_ids as $user_id) {
            Cache::forget($this->buildCacheKey($user_id, $permission->id));
        }
    }

    /**
     * Build the ACL cache key for a specific user and permission.
     */
    private function buildCacheKey(int|string $user_id, int|string $perm_id): string
    {
        return CacheManager::key('acl', 'user', (string) $user_id, 'perm', (string) $perm_id);
    }

    /**
     * Collect all user IDs that have the given permission, either directly
     * or through a role assignment.
     *
     * @return Collection<int, int|string>
     */
    private function getUserIdsForPermission(Permission $permission): Collection
    {
        $user_model = config('auth.providers.users.model', \App\Models\User::class);

        // Users with the permission assigned directly
        $direct_ids = DB::table(config('permission.table_names.model_has_permissions'))
            ->where('permission_id', $permission->id)
            ->where('model_type', $user_model)
            ->pluck('model_id');

        // Users with a role that has this permission
        $role_ids = DB::table(config('permission.table_names.role_has_permissions'))
            ->where('permission_id', $permission->id)
            ->pluck('role_id');

        $via_role_ids = collect();

        if ($role_ids->isNotEmpty()) {
            $via_role_ids = DB::table(config('permission.table_names.model_has_roles'))
                ->whereIn('role_id', $role_ids)
                ->where('model_type', $user_model)
                ->pluck('model_id');
        }

        return $direct_ids->merge($via_role_ids)->unique()->values();
    }

    /**
     * Flush all cache entries whose key starts with the 'acl:' namespace prefix.
     *
     * Compatible with database, file, and array cache drivers (no tag dependency).
     */
    private function flushAclPrefixedKeys(): void
    {
        $store = Cache::getStore();

        // Array driver: filter in-memory storage
        if ($store instanceof \Illuminate\Cache\ArrayStore) {
            $reflection = new ReflectionClass($store);
            $storage_prop = $reflection->getProperty('storage');

            /** @var array<string, mixed> $storage */
            $storage = $storage_prop->getValue($store);

            $acl_prefix = CacheManager::key('acl');

            foreach (array_keys($storage) as $key) {
                if (str_starts_with($key, $acl_prefix)) {
                    $store->forget($key);
                }
            }

            return;
        }

        // Database driver: DELETE WHERE key LIKE 'acl:%'
        if ($store instanceof \Illuminate\Cache\DatabaseStore) {
            $acl_prefix = CacheManager::key('acl');
            $cache_table = (string) config('cache.stores.database.table', 'cache');
            DB::table($cache_table)
                ->where('key', 'like', $acl_prefix . '%')
                ->delete();

            return;
        }

        // File driver: scan directory and delete matching files
        if ($store instanceof \Illuminate\Cache\FileStore) {
            $acl_prefix = CacheManager::key('acl');
            $directory = $store->getDirectory();

            if (is_dir($directory)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                );

                foreach ($iterator as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }

                    $contents = @file_get_contents($file->getPathname());

                    if ($contents === false) {
                        continue;
                    }

                    // The file store serializes the value; the key is not in the file content.
                    // We use the hashed filename to match — skip this approach and use forget().
                    // Instead, read the key from the store's path hashing.
                    // Since we cannot reverse the hash, we flush all ACL keys by iterating
                    // known user/permission combinations is not feasible here.
                    // Fall back to a full flush of the file cache as a safe degradation.
                }

                // Safe fallback for file driver: flush entire cache
                // (file driver does not support prefix-based deletion without tag support)
                Cache::flush();
            }

            return;
        }

        // Unknown driver: safe fallback
        Cache::flush();
    }

    /**
     * Resolve ACLs for a user on a permission using a single batch query.
     *
     * Instead of issuing one query per role (N+1), this method:
     * 1. Collects all role IDs for the user (direct + ancestors for inheritance)
     * 2. Loads all relevant ACLs in a single `whereIn` query with eager-loaded `permission.roles`
     * 3. Reconstructs the inheritance logic (role → ancestor fallback) in memory
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

        /** @var Collection<int, Role> $roles */
        $roles = $user->roles;

        if ($roles->isEmpty()) {
            return collect();
        }

        // Build a map of role_id → Role for all direct roles and their ancestors.
        // This is needed to reconstruct the inheritance chain in memory.
        /** @var Collection<int, Role> $all_roles_map */
        $all_roles_map = collect();

        foreach ($roles as $role) {
            $all_roles_map->put($role->id, $role);

            foreach ($role->ancestors as $ancestor) {
                $all_roles_map->put($ancestor->id, $ancestor);
            }
        }

        $all_role_ids = $all_roles_map->keys()->all();

        // Single batch query: load all active ACLs for this permission
        // that are associated with any of the user's roles (direct or ancestor).
        // Eager-load permission.roles to avoid N+1 in the matching logic below.
        /** @var Collection<int, ACL> $batch_acls */
        $batch_acls = ACL::query()
            ->active()
            ->forPermission($permission->id)
            ->byPriority()
            ->with(['permission.roles'])
            ->whereHas('permission.roles', static function (Builder $query) use ($all_role_ids): void {
                $query->whereIn($query->qualifyColumn('id'), $all_role_ids);
            })
            ->get();

        // Build a lookup: role_id → ACL (highest priority ACL for that role, already ordered)
        /** @var array<int, ACL> $acl_by_role */
        $acl_by_role = [];

        foreach ($batch_acls as $acl) {
            // permission.roles is already eager-loaded — no extra queries here
            $acl_role_ids = $acl->permission->roles->pluck('id')->all();

            foreach ($acl_role_ids as $acl_role_id) {
                // Only map roles that are in our set; keep the first (highest priority) ACL per role
                if (in_array($acl_role_id, $all_role_ids, true) && ! isset($acl_by_role[$acl_role_id])) {
                    $acl_by_role[$acl_role_id] = $acl;
                }
            }
        }

        // Reconstruct inheritance logic in memory for each direct role
        $resolved_acls = collect();

        foreach ($roles as $role) {
            // Skip roles that don't have this permission (direct or via ancestors)
            if (! $role->hasPermission($permission->name)) {
                continue;
            }

            $acl = $this->resolveAclFromMapForRole($role, $acl_by_role);

            if ($acl instanceof ACL) {
                $resolved_acls->push($acl);
            }
        }

        return $resolved_acls;
    }

    /**
     * Resolve ACL for a role using the pre-loaded in-memory map.
     * Implements inheritance: if role has no ACL in the map, check ancestor roles.
     *
     * @param  array<int, ACL>  $acl_by_role
     */
    private function resolveAclFromMapForRole(Role $role, array $acl_by_role): ?ACL
    {
        // Direct match: this role has an ACL
        if (isset($acl_by_role[$role->id])) {
            return $acl_by_role[$role->id];
        }

        // Inheritance fallback: walk ancestors from closest to root
        foreach ($role->ancestors as $ancestor) {
            if (isset($acl_by_role[$ancestor->id])) {
                return $acl_by_role[$ancestor->id];
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
        $acl->is_active = true;
        $acl->priority = PHP_INT_MAX;

        return $acl;
    }
}
