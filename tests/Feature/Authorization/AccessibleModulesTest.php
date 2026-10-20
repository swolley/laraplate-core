<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;

/**
 * The generated `permissions.module_name` column is the prefix of the entity table
 * (`default.cms_contents.select` is the `cms` module), the same value `userHasModuleAccess` looks up.
 */
function modulePermission(string $module): Permission
{
    return Permission::findOrCreate("default.{$module}_things.select", 'web');
}

function accessibleModulesOf(User $user): array
{
    return app(AuthorizationService::class)->accessibleModules($user)->sort()->values()->all();
}

it('returns the modules of direct permissions', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(modulePermission('cms'));

    expect(accessibleModulesOf($user->refresh()))->toBe(['cms']);
});

it('returns the modules of role permissions', function (): void {
    $role = Role::findOrCreate('accessible_modules_role_' . uniqid(), 'web');
    $role->givePermissionTo(modulePermission('erp'));

    $user = User::factory()->create();
    $user->assignRole($role);

    expect(accessibleModulesOf($user->refresh()))->toBe(['erp']);
});

it('merges direct and role modules without duplicates', function (): void {
    $role = Role::findOrCreate('accessible_modules_role_' . uniqid(), 'web');
    $role->givePermissionTo(modulePermission('cms'));
    $role->givePermissionTo(modulePermission('mes'));

    $user = User::factory()->create();
    $user->assignRole($role);
    $user->givePermissionTo(modulePermission('cms'));

    expect(accessibleModulesOf($user->refresh()))->toBe(['cms', 'mes']);
});

it('gives a user without permissions an empty collection', function (): void {
    modulePermission('cms');

    expect(accessibleModulesOf(User::factory()->create()))->toBe([]);
});

it('gives a super admin every module that has permissions', function (): void {
    modulePermission('cms');
    modulePermission('erp');

    $super = User::factory()->create();
    $super->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    expect(accessibleModulesOf($super->refresh()))->toContain('cms', 'erp');
});

it('keeps a user to the modules they hold, not the ones that only exist', function (): void {
    modulePermission('cms');
    modulePermission('erp');

    $user = User::factory()->create();
    $user->givePermissionTo(modulePermission('cms'));

    expect(accessibleModulesOf($user->refresh()))->toBe(['cms']);
});

it('runs the same number of queries whatever the number of modules', function (): void {
    $countQueries = function (User $user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(AuthorizationService::class)->accessibleModules($user);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $few = User::factory()->create();
    $few->givePermissionTo(modulePermission('cms'));

    $many = User::factory()->create();

    foreach (['cms', 'erp', 'mes', 'sao', 'shop'] as $module) {
        $many->givePermissionTo(modulePermission($module));
    }

    expect($countQueries($many->refresh()))->toBe($countQueries($few->refresh()));
});
