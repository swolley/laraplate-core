<?php

declare(strict_types=1);

namespace Modules\Core\Services\Authorization;

use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;

/**
 * Authorization Service - handles permission checks and ACL filter injection.
 *
 * This service is the single point of entry for all authorization logic:
 * 1. Permission checks (can user perform operation on entity?)
 * 2. ACL resolution (what rows can user access?)
 * 3. ACL injection into requests (modify request filters with ACL constraints)
 *
 * Usage in CrudService:
 * ```php
 * $auth = app(AuthorizationService::class);
 * $permission_name = $auth->ensurePermission($request, 'orders', 'select');
 * $auth->injectAclFilters($requestData, $permission_name);
 * // Now requestData->filters includes ACL constraints
 * ```
 */
final class AuthorizationService
{
    public function __construct(
        private readonly AclResolverService $acl_resolver,
    ) {}

    /**
     * Check if user has permission for the requested operation.
     *
     * @param  Request  $request  The HTTP request (used to get user)
     * @param  string  $entity  The entity/table name
     * @param  string|null  $operation  The operation (select, insert, update, delete, etc.)
     * @param  string|null  $connection  The database connection name
     * @return bool True if user has permission
     */
    public function checkPermission(
        Request $request,
        string $entity,
        ?string $operation = null,
        ?string $connection = null,
    ): bool {
        /** @var SessionGuard $guard */
        $guard = Auth::guard();
        $guard_name = $guard->name;

        $permission_name = $this->buildPermissionName($entity, $operation, $connection);

        $user = $this->resolveUser($request);

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (bool) $user->hasPermissionTo($permission_name, $guard_name);
    }

    /**
     * Ensure user has permission or throw exception.
     *
     *
     * @throws UnauthorizedException If user doesn't have permission
     *
     * @return string The permission name that was checked (for ACL resolution)
     */
    public function ensurePermission(
        Request $request,
        string $entity,
        ?string $operation = null,
        ?string $connection = null,
    ): string {
        $permission_name = $this->buildPermissionName($entity, $operation, $connection);

        throw_unless(
            $this->checkPermission($request, $entity, $operation, $connection),
            UnauthorizedException::class,
            'User not allowed to access this resource',
        );

        return $permission_name;
    }

    /**
     * Get ACL filters for the current user on a permission.
     *
     * @param  string  $permission_name  The full permission name (e.g., 'default.orders.select')
     * @return FiltersGroup|null The ACL filters, or null if unrestricted
     */
    public function getAclFilters(string $permission_name): ?FiltersGroup
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        $permission = Permission::findByName($permission_name);

        if ($permission === null) {
            return null;
        }

        return $this->acl_resolver->getCombinedFilters($user, $permission);
    }

    /**
     * Inject ACL filters into the request data.
     *
     * This method modifies the request's filters to include ACL constraints.
     * The logic wraps existing user filters with ACL filters using AND:
     *
     * Before: filters = { user_filters }
     * After:  filters = { ACL_filters AND user_filters }
     *
     * This ensures users cannot bypass ACL restrictions with their own filters.
     *
     * @param  ListRequestData  $request_data  The request data to modify
     * @param  string  $permission_name  The permission name for ACL lookup
     */
    public function injectAclFilters(ListRequestData $request_data, string $permission_name): void
    {
        $acl_filters = $this->getAclFilters($permission_name);

        if ($acl_filters === null) {
            return;
        }

        $request_data->mergeFilters($acl_filters);
    }

    /**
     * Check if user has unrestricted access to a permission.
     *
     * @param  string  $permission_name  The full permission name
     * @return bool True if user has no ACL restrictions
     */
    public function hasUnrestrictedAccess(string $permission_name): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = Permission::findByName($permission_name);

        if ($permission === null) {
            return true;
        }

        return $this->acl_resolver->hasUnrestrictedAccess($user, $permission);
    }

    /**
     * Build the full permission name from components.
     */
    public function buildPermissionName(
        string $entity,
        ?string $operation = null,
        ?string $connection = null,
    ): string {
        $connection ??= 'default';

        return sprintf('%s.%s.%s', $connection, $entity, $operation);
    }

    /**
     * Apply ACL filters directly to a query builder.
     *
     * Use this for requests that don't have a filters property (e.g., DetailRequestData).
     * For ListRequestData, prefer injectAclFilters() to modify the request.
     *
     * @param  Builder  $query  The Eloquent query builder
     * @param  string  $permission_name  The permission name for ACL lookup
     */
    public function applyAclFiltersToQuery(Builder $query, string $permission_name): void
    {
        $acl_filters = $this->getAclFilters($permission_name);

        if ($acl_filters === null) {
            return;
        }

        // Apply filters using a closure to wrap them properly
        $query->where(function (Builder $q) use ($acl_filters): void {
            $this->applyFiltersRecursively($q, $acl_filters);
        });
    }

    /**
     * Clear ACL cache for the current user.
     */
    public function clearCacheForCurrentUser(): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->acl_resolver->clearCacheForUser($user);
        }
    }

    /**
     * Apply filters recursively to a query.
     */
    private function applyFiltersRecursively(Builder $query, FiltersGroup $filters): void
    {
        $method = $filters->operator === WhereClause::AND ? 'where' : 'orWhere';

        foreach ($filters->filters as $filter) {
            if ($filter instanceof FiltersGroup) {
                $query->{$method}(function (Builder $q) use ($filter): void {
                    $this->applyFiltersRecursively($q, $filter);
                });
            } else {
                // It's a Filter
                $this->applySingleFilter($query, $filter, $method);
            }
        }
    }

    /**
     * Apply a single filter to a query.
     */
    private function applySingleFilter(Builder $query, mixed $filter, string $method): void
    {
        if ($filter->value === null) {
            $null_method = $filter->operator->value === '=' ? 'whereNull' : 'whereNotNull';

            if ($method === 'orWhere') {
                $null_method = 'or' . ucfirst($null_method);
            }
            $query->{$null_method}($filter->property);

            return;
        }

        if ($filter->operator->value === 'in') {
            $in_method = $method === 'orWhere' ? 'orWhereIn' : 'whereIn';
            $query->{$in_method}($filter->property, (array) $filter->value);

            return;
        }

        if ($filter->operator->value === 'between' && is_array($filter->value)) {
            $between_method = $method === 'orWhere' ? 'orWhereBetween' : 'whereBetween';
            $query->{$between_method}($filter->property, $filter->value);

            return;
        }

        $query->{$method}($filter->property, $filter->operator->value, $filter->value);
    }

    /**
     * Resolve the user from request, falling back to anonymous user if needed.
     */
    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();

        if ($user !== null) {
            return $user;
        }

        // Try to get anonymous user
        $anonymous = Cache::rememberForever(
            'anonymous_user',
            static fn () => user_class()::whereName('anonymous')->first(),
        );

        if ($anonymous === null) {
            return null;
        }

        Auth::login($anonymous);
        $request->setUserResolver(fn () => $anonymous);

        return $anonymous;
    }
}
