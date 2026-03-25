<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
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
    $superRole = Role::factory()->create(['name' => 'superadmin', 'guard_name' => 'web']);

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

it('getEffectiveAcls is empty when the user has no roles', function (): void {
    $user = User::factory()->create();
    $permission = Permission::create([
        'name' => 'default.acl_norole_' . uniqid() . '.select',
        'guard_name' => 'web',
    ]);

    $service = new AclResolverService();

    expect($service->getEffectiveAcls($user, $permission))->toBeEmpty();
});

it('getCombinedFilters returns stored filters when a role has an active ACL for the permission', function (): void {
    $permission = Permission::create([
        'name' => 'default.acl_filters_' . uniqid() . '.select',
        'guard_name' => 'web',
    ]);

    $role = Role::factory()->create(['name' => 'acl_editor_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $filter_group = new FiltersGroup([
        new Filter('status', 'published', FilterOperator::EQUALS),
    ]);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $filter_group,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);

    $service = new AclResolverService();
    $combined = $service->getCombinedFilters($user, $permission);

    expect($combined)->toBeInstanceOf(FiltersGroup::class)
        ->and($combined->operator)->toBe(WhereClause::AND)
        ->and($combined->filters)->toHaveCount(1)
        ->and($combined->filters[0])->toBeInstanceOf(Filter::class)
        ->and($combined->filters[0]->property)->toBe('status')
        ->and($combined->filters[0]->value)->toBe('published');

    expect($service->hasUnrestrictedAccess($user, $permission))->toBeFalse();
});

it('getCombinedFilters wraps multiple contributing ACLs with OR', function (): void {
    $permission = Permission::create([
        'name' => 'default.acl_or_' . uniqid() . '.select',
        'guard_name' => 'web',
    ]);

    $filter_group = new FiltersGroup([
        new Filter('region', 'it', FilterOperator::EQUALS),
    ]);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $filter_group,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $role_a = Role::factory()->create(['name' => 'acl_ra_' . uniqid(), 'guard_name' => 'web']);
    $role_b = Role::factory()->create(['name' => 'acl_rb_' . uniqid(), 'guard_name' => 'web']);
    $role_a->givePermissionTo($permission);
    $role_b->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole([$role_a, $role_b]);

    $service = new AclResolverService();
    $combined = $service->getCombinedFilters($user, $permission);

    expect($combined)->toBeInstanceOf(FiltersGroup::class)
        ->and($combined->operator)->toBe(WhereClause::OR)
        ->and($combined->filters)->toHaveCount(2);
});

it('inherits ACL filters from an ancestor role when the direct role has no ACL row', function (): void {
    $permission = Permission::create([
        'name' => 'default.acl_inherit_' . uniqid() . '.select',
        'guard_name' => 'web',
    ]);

    $parent = Role::factory()->create(['name' => 'acl_parent_' . uniqid(), 'guard_name' => 'web']);
    $parent->givePermissionTo($permission);

    $child = Role::factory()->create(['name' => 'acl_child_' . uniqid(), 'guard_name' => 'web']);
    $child->forceFill(['parent_id' => $parent->id])->saveQuietly();

    $filter_group = new FiltersGroup([
        new Filter('tenant_id', 42, FilterOperator::EQUALS),
    ]);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $filter_group,
        'unrestricted' => false,
        'priority' => 5,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($child);

    expect($child->hasPermission($permission->name))->toBeTrue();

    $service = new AclResolverService();
    $combined = $service->getCombinedFilters($user, $permission);

    expect($combined)->toBeInstanceOf(FiltersGroup::class)
        ->and($combined->filters[0])->toBeInstanceOf(Filter::class)
        ->and($combined->filters[0]->property)->toBe('tenant_id')
        ->and($combined->filters[0]->value)->toBe(42);
});

it('treats unrestricted ACL rows as non contributing for combined filters', function (): void {
    $permission = Permission::create([
        'name' => 'default.acl_unres_' . uniqid() . '.select',
        'guard_name' => 'web',
    ]);

    $role = Role::factory()->create(['name' => 'acl_unres_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => null,
        'unrestricted' => true,
        'priority' => 1,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);

    $service = new AclResolverService();

    expect($service->getCombinedFilters($user, $permission))->toBeNull()
        ->and($service->hasUnrestrictedAccess($user, $permission))->toBeTrue();
});

it('ignores roles that lack the permission when resolving effective ACLs', function (): void {
    $permission = Permission::create([
        'name' => 'default.acl_skip_role_' . uniqid() . '.select',
        'guard_name' => 'web',
    ]);

    $role_with_acl = Role::factory()->create(['name' => 'acl_has_' . uniqid(), 'guard_name' => 'web']);
    $role_with_acl->givePermissionTo($permission);

    $filter_group = new FiltersGroup([
        new Filter('region', 'eu', FilterOperator::EQUALS),
    ]);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $filter_group,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $role_without_permission = Role::factory()->create(['name' => 'acl_noperm_' . uniqid(), 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole([$role_without_permission, $role_with_acl]);

    $service = new AclResolverService();
    $acls = $service->getEffectiveAcls($user, $permission);

    expect($acls)->toHaveCount(1)
        ->and($acls->first()->filters)->toEqual($filter_group);
});
