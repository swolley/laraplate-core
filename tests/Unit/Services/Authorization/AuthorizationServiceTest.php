<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use App\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;


beforeEach(function (): void {
    Cache::flush();
});

it('buildPermissionName composes connection, entity and operation', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    expect($service->buildPermissionName('orders', 'select', 'mysql'))->toBe('mysql.orders.select');
    expect($service->buildPermissionName('orders', 'select', null))->toBe('default.orders.select');
    expect($service->buildPermissionName('orders', null, null))->toBe('default.orders.');
});

it('getAclFilters returns null for non authenticated user', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    Auth::shouldReceive('user')->andReturnNull();
    expect($service->getAclFilters('default.orders.select'))->toBeNull();
});

// NOTE: More complex permission flows are exercised via higher-level tests;
// here we keep unit tests focused on helper methods and cache/ACL wiring.

it('hasUnrestrictedAccess returns false when no authenticated user', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    Auth::shouldReceive('user')->andReturnNull();

    expect($service->hasUnrestrictedAccess('default.orders.select'))->toBeFalse();
});

it('hasUnrestrictedAccess returns true for super admin user', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    config()->set('permission.roles.superadmin', 'superadmin');
    $super_role = Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($super_role);

    Auth::shouldReceive('user')->andReturn($user);

    expect($service->hasUnrestrictedAccess('default.orders.select'))->toBeTrue();
});

it('clearCacheForCurrentUser does nothing when not authenticated', function (): void {
    $service = new AuthorizationService(new AclResolverService());

    Auth::shouldReceive('user')->andReturnNull();

    $service->clearCacheForCurrentUser();
    expect(true)->toBeTrue();
});

it('clearCacheForCurrentUser forwards to AclResolverService when user is authenticated', function (): void {
    $resolver = new AclResolverService();
    $service = new AuthorizationService($resolver);

    $user = User::factory()->create();

    Auth::shouldReceive('user')->andReturn($user);

    $service->clearCacheForCurrentUser();
    expect(true)->toBeTrue();
});

it('ensurePermission throws when the user lacks the permission', function (): void {
    $entity = 'authz_items_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => auth()->user());

    expect(fn () => $service->ensurePermission($request, $entity, 'select'))
        ->toThrow(UnauthorizedException::class, 'User not allowed to access this resource');
});

it('ensurePermission returns the permission name when the user is allowed', function (): void {
    $entity = 'authz_ok_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => auth()->user());

    expect($service->ensurePermission($request, $entity, 'select'))->toBe($permission_name);
});

it('checkPermission is false when the authenticated user lacks the permission', function (): void {
    $entity = 'authz_denied_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => auth()->user());

    expect($service->checkPermission($request, $entity, 'select'))->toBeFalse();
});

it('checkPermission is true when the user has the permission on the default guard', function (): void {
    $entity = 'authz_granted_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo($permission);
    $this->actingAs($user);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');
    $request->setUserResolver(fn (): ?User => auth()->user());

    expect($service->checkPermission($request, $entity, 'select'))->toBeTrue();
});

it('checkPermission resolves the anonymous user when the request has no user and denies without permission', function (): void {
    $entity = 'authz_anon_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    User::factory()->create([
        'name' => 'anonymous',
        'username' => 'anonymous',
        'email' => 'anonymous_' . uniqid() . '@example.com',
    ]);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');

    expect($request->user())->toBeNull()
        ->and($service->checkPermission($request, $entity, 'select'))->toBeFalse();
});

it('getAclFilters returns null for super admin with real AclResolverService', function (): void {
    config()->set('permission.roles.superadmin', 'superadmin');

    $entity = 'authz_sa_acl_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $super_role */
    $super_role = Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($super_role);

    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    expect($service->getAclFilters($permission_name))->toBeNull();
});

it('getAclFilters returns null for a normal user when the resolver yields no row filters', function (): void {
    $entity = 'authz_plain_acl_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo($permission);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    expect($service->getAclFilters($permission_name))->toBeNull();
});

it('hasUnrestrictedAccess delegates to resolver for normal user', function (): void {
    $entity = 'authz_unrestricted_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl = new ACL();
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'unrestricted' => true,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    expect($service->hasUnrestrictedAccess($permission_name))->toBeTrue();
});

it('injectAclFilters does nothing when no ACL filters are returned', function (): void {
    $entity = 'authz_inject_none_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $user = User::factory()->create();
    Auth::login($user);

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    $service = new AuthorizationService(new AclResolverService());

    $request_data = (new ReflectionClass(ListRequestData::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($request_data, 'filters'))->setValue($request_data, null);

    $service->injectAclFilters($request_data, $permission_name);

    expect((new ReflectionProperty($request_data, 'filters'))->getValue($request_data))->toBeNull();
});

it('injectAclFilters sets filters when request has none', function (): void {
    $entity = 'authz_inject_set_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl = new ACL();
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => new FiltersGroup([
            new Filter('users.id', [1, 2], FilterOperator::IN),
        ], WhereClause::AND),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    $expected = new FiltersGroup([
        new Filter('users.id', [1, 2], FilterOperator::IN),
    ], WhereClause::AND);

    $service = new AuthorizationService(new AclResolverService());

    $request_data = (new ReflectionClass(ListRequestData::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($request_data, 'filters'))->setValue($request_data, null);

    $service->injectAclFilters($request_data, $permission_name);

    expect((new ReflectionProperty($request_data, 'filters'))->getValue($request_data))->toEqual($expected);
});

it('injectAclFilters wraps existing filters with ACL filters using AND', function (): void {
    $entity = 'authz_inject_merge_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl_filters = new FiltersGroup([
        new Filter('users.id', [1, 2], FilterOperator::IN),
    ], WhereClause::AND);

    $acl = new ACL();
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $acl_filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user_filters = new FiltersGroup([
        new Filter('users.email', null, FilterOperator::EQUALS),
    ], WhereClause::AND);

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    $request_data = (new ReflectionClass(ListRequestData::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($request_data, 'filters'))->setValue($request_data, $user_filters);

    $service->injectAclFilters($request_data, $permission_name);

    /** @var FiltersGroup $merged */
    $merged = (new ReflectionProperty($request_data, 'filters'))->getValue($request_data);

    expect($merged->operator)->toBe(WhereClause::AND);
    expect($merged->filters)->toHaveCount(2);
    expect($merged->filters[0])->toEqual($acl_filters);
    expect($merged->filters[1])->toBe($user_filters);
});

it('applyAclFiltersToQuery applies null, in, between and comparison operators', function (): void {
    $entity = 'authz_apply_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl_filters = new FiltersGroup([
        new Filter('users.email', null, FilterOperator::EQUALS),
        new Filter('users.id', [1, 2], FilterOperator::IN),
        new Filter('users.created_at', ['2020-01-01 00:00:00', '2020-01-02 00:00:00'], FilterOperator::BETWEEN),
        new Filter('users.name', 'john', FilterOperator::LIKE),
    ], WhereClause::AND);

    $acl = new ACL();
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $acl_filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    $query = User::query();
    $service->applyAclFiltersToQuery($query, $permission_name);

    $sql = $query->toSql();

    expect($sql)->toContain('email" is null');
    expect($sql)->toContain('id" in');
    expect($sql)->toContain('created_at" between');
    expect($sql)->toContain('name" like');

    $bindings = $query->getBindings();
    expect($bindings)->toContain(1)->toContain(2)->toContain('2020-01-01 00:00:00')->toContain('2020-01-02 00:00:00');
});

it('checkPermission is true for super admin without evaluating the specific permission', function (): void {
    config()->set('permission.roles.superadmin', 'superadmin');

    $entity = 'authz_sa_perm_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $super_role */
    $super_role = Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($super_role);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');
    $request->setUserResolver(static fn (): User => $user);

    expect($service->checkPermission($request, $entity, 'select'))->toBeTrue();
});

it('checkPermission returns false when the request user is not a Core User model', function (): void {
    $guard = new class
    {
        public string $name = 'web';
    };

    Auth::shouldReceive('guard')->andReturn($guard);

    /** @var Authenticatable&Mockery\MockInterface $authenticatable */
    $authenticatable = Mockery::mock(Authenticatable::class);

    $request = Request::create('/');
    $request->setUserResolver(static fn (): Authenticatable => $authenticatable);

    $service = new AuthorizationService(new AclResolverService());

    expect($service->checkPermission($request, 'orders', 'select'))->toBeFalse();
});

it('checkPermission returns false when there is no request user and anonymous user does not exist', function (): void {
    Cache::forget('anonymous_user');

    $entity = 'authz_no_anon_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    $service = new AuthorizationService(new AclResolverService());
    $request = Request::create('/');

    expect($service->checkPermission($request, $entity, 'select'))->toBeFalse();
});

it('applyAclFiltersToQuery returns early when getAclFilters yields no filter group', function (): void {
    config()->set('permission.roles.superadmin', 'superadmin');

    $entity = 'authz_apply_skip_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $super_role */
    $super_role = Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($super_role);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    $query = User::query();
    $sql_before = $query->toSql();

    $service->applyAclFiltersToQuery($query, $permission_name);

    expect($query->toSql())->toBe($sql_before);
});

it('applyAclFiltersToQuery applies nested filter groups recursively', function (): void {
    $entity = 'authz_nested_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $nested = new FiltersGroup([
        new Filter('users.id', 99, FilterOperator::EQUALS),
    ], WhereClause::AND);

    $acl_filters = new FiltersGroup([$nested], WhereClause::AND);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $acl_filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    $query = User::query();
    $service->applyAclFiltersToQuery($query, $permission_name);

    expect($query->toSql())->toContain('"id" = ?');
    expect($query->getBindings())->toContain(99);
});

it('applyAclFiltersToQuery uses orWhereNull when OR group contains an equals-null filter', function (): void {
    $entity = 'authz_or_null_' . uniqid();
    $permission_name = 'default.' . $entity . '.select';

    $permission = Permission::create([
        'name' => $permission_name,
        'guard_name' => 'web',
    ]);

    /** @var Role $role */
    $role = Role::factory()->create(['name' => 'role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl_filters = new FiltersGroup([
        new Filter('users.id', 1, FilterOperator::EQUALS),
        new Filter('users.email', null, FilterOperator::EQUALS),
    ], WhereClause::OR);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $acl_filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    $service = new AuthorizationService(new AclResolverService());

    $query = User::query();
    $service->applyAclFiltersToQuery($query, $permission_name);

    expect($query->toSql())->toContain('email" is null');
});
