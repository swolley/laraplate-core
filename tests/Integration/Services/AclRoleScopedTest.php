<?php

declare(strict_types=1);

use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\AclResolverService;

/**
 * @param  array<string, mixed>  $attributes
 */
function make_acl(Permission $permission, FiltersGroup $filters, array $attributes = []): ACL
{
    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill(array_merge([
        'permission_id' => $permission->id,
        'filters' => $filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ], $attributes));
    $acl->save();

    return $acl;
}

function scoped_permission(): Permission
{
    return Permission::create(['name' => 'default.acl_scope_' . uniqid() . '.select', 'guard_name' => 'web']);
}

function scoped_filters(): FiltersGroup
{
    return new FiltersGroup([new Filter('status', 'published', FilterOperator::Equals)]);
}

it('applies a role-scoped ACL only to its own role', function (): void {
    $permission = scoped_permission();

    $guest = Role::factory()->create(['name' => 'scope_guest_' . uniqid(), 'guard_name' => 'web']);
    $staff = Role::factory()->create(['name' => 'scope_staff_' . uniqid(), 'guard_name' => 'web']);
    $guest->givePermissionTo($permission);
    $staff->givePermissionTo($permission);

    make_acl($permission, scoped_filters(), ['role_id' => $guest->id]);

    $guest_user = User::factory()->create();
    $guest_user->assignRole($guest);
    $staff_user = User::factory()->create();
    $staff_user->assignRole($staff);

    $service = new AclResolverService;

    // The guest is constrained by the scoped ACL...
    expect($service->getCombinedFilters($guest_user, $permission))->toBeInstanceOf(FiltersGroup::class);

    // ...while the staff role, sharing the same permission, is unrestricted.
    expect($service->getCombinedFilters($staff_user, $permission))->toBeNull();
});

it('keeps an unscoped ACL applying to every role holding the permission', function (): void {
    $permission = scoped_permission();

    $role_a = Role::factory()->create(['name' => 'scope_a_' . uniqid(), 'guard_name' => 'web']);
    $role_b = Role::factory()->create(['name' => 'scope_b_' . uniqid(), 'guard_name' => 'web']);
    $role_a->givePermissionTo($permission);
    $role_b->givePermissionTo($permission);

    // role_id null == legacy behavior: applies to all roles with the permission.
    make_acl($permission, scoped_filters(), ['role_id' => null]);

    $user_a = User::factory()->create();
    $user_a->assignRole($role_a);
    $user_b = User::factory()->create();
    $user_b->assignRole($role_b);

    $service = new AclResolverService;

    expect($service->getCombinedFilters($user_a, $permission))->toBeInstanceOf(FiltersGroup::class)
        ->and($service->getCombinedFilters($user_b, $permission))->toBeInstanceOf(FiltersGroup::class);
});

it('prefers a higher-priority scoped ACL over an unscoped one for the same role', function (): void {
    $permission = scoped_permission();

    $role = Role::factory()->create(['name' => 'scope_pref_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    make_acl($permission, scoped_filters(), ['role_id' => null, 'priority' => 5]);
    make_acl($permission, scoped_filters(), ['role_id' => $role->id, 'unrestricted' => true, 'priority' => 20]);

    $user = User::factory()->create();
    $user->assignRole($role);

    // The unrestricted, higher-priority role-scoped ACL wins → no filters.
    expect((new AclResolverService)->getCombinedFilters($user, $permission))->toBeNull();
});

it('lets a child role override an inherited parent ACL with its own unrestricted ACL', function (): void {
    $permission = scoped_permission();

    $parent = Role::factory()->create(['name' => 'scope_parent_' . uniqid(), 'guard_name' => 'web']);
    $child = Role::factory()->create(['name' => 'scope_child_' . uniqid(), 'guard_name' => 'web', 'parent_id' => $parent->id]);
    $parent->givePermissionTo($permission);
    $child->givePermissionTo($permission);

    // Only the parent carries a restrictive ACL.
    make_acl($permission, scoped_filters(), ['role_id' => $parent->id]);

    $child_user = User::factory()->create();
    $child_user->assignRole($child);

    $service = new AclResolverService;

    // With no ACL of its own, the child inherits the parent's restrictive ACL.
    expect($service->getCombinedFilters($child_user, $permission))->toBeInstanceOf(FiltersGroup::class);

    // Giving the child its own unrestricted ACL overrides the inherited one. The ACL
    // observer flushes the resolver cache, so the override takes effect immediately.
    make_acl($permission, scoped_filters(), ['role_id' => $child->id, 'unrestricted' => true, 'priority' => 50]);

    expect((new AclResolverService)->getCombinedFilters($child_user->fresh(), $permission))->toBeNull();
});
