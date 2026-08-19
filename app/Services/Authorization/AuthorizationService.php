<?php

declare(strict_types=1);

namespace Modules\Core\Services\Authorization;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Support\PermissionName;

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
    /**
     * Static in-memory cache for resolved Permission model instances, keyed by permission name.
     * Populated on first access; avoids repeated Permission::findByName() DB queries per request.
     *
     * @var array<string, Permission>
     */
    private static array $permission_model_cache = [];

    public function __construct(
        private readonly AclResolverService $acl_resolver,
    ) {}

    /**
     * Reset the static permission model cache.
     *
     * Intended for use in tests to ensure a clean state between test cases.
     */
    public static function resetPermissionCache(): void
    {
        self::$permission_model_cache = [];
    }

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

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermissionTo($permission_name, $guard_name);
    }

    /**
     * The operations the current user may perform on the given entity/table.
     *
     * Drives capability-based UI: the SPA reads these instead of reconstructing
     * the `{connection}.{table}.{operation}` permission names. `impersonate` is a
     * global user capability, not an entity operation, so it is excluded.
     *
     * @return list<string> Allowed ActionEnum values, e.g. ['select', 'update', 'approve'].
     */
    public function allowedOperations(Request $request, string $entity, ?string $connection = null): array
    {
        $operations = [];

        foreach (ActionEnum::cases() as $action) {
            if ($action === ActionEnum::Impersonate) {
                continue;
            }

            if ($this->checkPermission($request, $entity, $action->value, $connection)) {
                $operations[] = $action->value;
            }
        }

        return $operations;
    }

    /**
     * Ensure user has permission or throw exception.
     *
     *
     * @throws AuthorizationException If user doesn't have permission
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
            AuthorizationException::class,
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

        $permission = $this->resolvePermission($permission_name);

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

        if (! $acl_filters instanceof FiltersGroup) {
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

        $permission = $this->resolvePermission($permission_name);

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
        return PermissionName::build($connection ?? 'default', $entity, (string) $operation);
    }

    /**
     * Apply ACL filters directly to a query builder.
     *
     * Use this for requests that don't have a filters property (e.g., DetailRequestData).
     * For ListRequestData, prefer injectAclFilters() to modify the request.
     *
     * Generic over the model: a caller holding a Builder<Ticket> or any other
     * concrete builder must be able to pass it. Declaring Builder<Model> made
     * every such call an argument.type error, since PHPStan treats the builder
     * as invariant in its model.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  The Eloquent query builder
     * @param  string  $permission_name  The permission name for ACL lookup
     */
    public function applyAclFiltersToQuery(Builder $query, string $permission_name): void
    {
        $acl_filters = $this->getAclFilters($permission_name);

        if (! $acl_filters instanceof FiltersGroup) {
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
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applyFiltersRecursively(Builder $query, FiltersGroup $filters): void
    {
        $method = $filters->operator === WhereClause::And ? 'where' : 'orWhere';

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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function applySingleFilter(Builder $query, Filter $filter, string $method): void
    {
        if ($filter->value === null) {
            $null_method = $filter->operator->value === '=' ? 'whereNull' : 'whereNotNull';

            if ($method === 'orWhere') {
                $null_method = 'or' . ucfirst($null_method);
            }
            $query->{$null_method}($filter->property);

            return;
        }

        $value = $this->resolveFilterValue($filter->value);

        if ($filter->operator->value === 'in') {
            $in_method = $method === 'orWhere' ? 'orWhereIn' : 'whereIn';
            $query->{$in_method}($filter->property, (array) $value);

            return;
        }

        if ($filter->operator->value === 'between' && is_array($value)) {
            $between_method = $method === 'orWhere' ? 'orWhereBetween' : 'whereBetween';
            $query->{$between_method}($filter->property, $value);

            return;
        }

        $query->{$method}($filter->property, $filter->operator->value, $value);
    }

    /**
     * Resolve dynamic placeholders in an ACL filter value at query-build time.
     *
     * ACL filters are JSON-persisted with frozen literal values, so a stored value
     * cannot express a moving target such as the current time. A value equal to a
     * known placeholder token (e.g. `@now`, `@today`) is substituted with its live
     * value here; array values (for `in` / `between`) are resolved element-wise, and
     * any other value is returned untouched.
     */
    private function resolveFilterValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->resolveFilterValue($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return match ($value) {
            '@now' => Carbon::now(),
            '@today' => Carbon::today(),
            default => $value,
        };
    }

    /**
     * Resolve a Permission model instance by name, using the static in-memory cache.
     *
     * On first access the model is fetched via Permission::findByName() and stored in the cache.
     * Subsequent calls for the same name return the cached instance without a DB query.
     *
     * @param  string  $permission_name  The full permission name (e.g., 'default.orders.select')
     */
    private function resolvePermission(string $permission_name): Permission
    {
        if (! isset(self::$permission_model_cache[$permission_name])) {
            self::$permission_model_cache[$permission_name] = Permission::query()
                ->where('name', $permission_name)
                ->firstOrFail();
        }

        return self::$permission_model_cache[$permission_name];
    }

    /**
     * Resolve the user from request, falling back to anonymous user if needed.
     */
    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        // Try to get anonymous user
        $anonymous = Cache::rememberForever(
            'anonymous_user',
            static fn (): ?User => User::query()->where('name', 'anonymous')->first(),
        );

        if ($anonymous === null) {
            return null;
        }

        Auth::login($anonymous);
        $request->setUserResolver(fn (): User => $anonymous);

        return $anonymous;
    }
}
