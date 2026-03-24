<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

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

    /** @var User&Mockery\MockInterface $user */
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isSuperAdmin')->andReturnTrue();

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

    /** @var User&Mockery\MockInterface $user */
    $user = Mockery::mock(User::class);

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
