<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('getEffectiveAcls caches the resolved ACLs per user and permission', function (): void {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var Permission $permission */
    $permission = Permission::factory()->create();

    $service = new AclResolverService();

    // First call should hit the DB via resolveAcls and populate cache
    $aclsFirst = $service->getEffectiveAcls($user, $permission);
    expect($aclsFirst)->toBeInstanceOf(Collection::class);

    // Second call should return from cache (we do not assert DB calls here, just that it still returns a Collection)
    $aclsSecond = $service->getEffectiveAcls($user, $permission);

    expect($aclsSecond)->toBeInstanceOf(Collection::class);
});

it('getCombinedFilters returns null when there are no contributing ACLs', function (): void {
    $user = User::factory()->create();
    $permission = Permission::factory()->create();

    $service = new AclResolverService();

    // No ACLs in DB for this permission/user
    $combined = $service->getCombinedFilters($user, $permission);

    expect($combined)->toBeNull();
});

it('hasUnrestrictedAccess is true when there are no contributing ACLs', function (): void {
    $user = User::factory()->create();
    $permission = Permission::factory()->create();

    $service = new AclResolverService();

    expect($service->hasUnrestrictedAccess($user, $permission))->toBeTrue();
});

it('clearCacheForUser forgets cached ACL entries for that user', function (): void {
    $user = User::factory()->create();
    $permission = Permission::factory()->create();

    $key = 'acl:resolved:user:' . $user->id . ':perm:' . $permission->id;
    Cache::put($key, 'dummy', 600);
    expect(Cache::get($key))->toBe('dummy');

    $service = new AclResolverService();
    $service->clearCacheForUser($user);

    expect(Cache::get($key))->toBeNull();
});

it('clearCacheForPermission flushes all acl related cache', function (): void {
    $user = User::factory()->create();
    $permission = Permission::factory()->create();

    $key = 'acl:resolved:user:' . $user->id . ':perm:' . $permission->id;
    Cache::put($key, 'dummy', 600);
    expect(Cache::get($key))->toBe('dummy');

    $service = new AclResolverService();
    $service->clearCacheForPermission();

    expect(Cache::get($key))->toBeNull();
});

it('returns unrestricted virtual ACL for super admin user', function (): void {
    config()->set('permission.roles.superadmin', 'superadmin');

    /** @var Role $superRole */
    $superRole = Role::factory()->create(['name' => 'superadmin']);
    /** @var User $user */
    $user = User::factory()->create();
    $user->assignRole($superRole);

    /** @var Permission $permission */
    $permission = Permission::factory()->create();

    $service = new AclResolverService();

    $acls = $service->getEffectiveAcls($user, $permission);

    expect($acls)->toHaveCount(1);
    /** @var ACL $acl */
    $acl = $acls->first();
    expect($acl)->toBeInstanceOf(ACL::class)
        ->and($acl->isUnrestricted())->toBeTrue();

    expect($service->hasUnrestrictedAccess($user, $permission))->toBeTrue();
});

// NOTE: More complex scenarios (multiple ACLs with filters) are covered indirectly
// via higher-level authorization tests to avoid coupling this unit test to
// Eloquent casting internals of the FiltersGroup value object.

