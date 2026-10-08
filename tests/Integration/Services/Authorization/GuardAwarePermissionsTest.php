<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Services\AclResolverService;
use Modules\Core\Services\Authorization\AuthorizationService;

beforeEach(function (): void {
    Cache::flush();
    config()->set('permission.roles.superadmin', 'superadmin');
});

/**
 * @return array{web: Permission, api: Permission}
 */
function guardAwarePermissionPair(string $name): array
{
    return [
        'web' => Permission::query()->create(['name' => $name, 'guard_name' => 'web']),
        'api' => Permission::query()->create(['name' => $name, 'guard_name' => 'api']),
    ];
}

it('grants a role permission only on the guard of the role', function (string $role_guard, string $other_guard): void {
    $name = 'default.cms_tags.select';
    $permissions = guardAwarePermissionPair($name);

    $role = Role::factory()->create(['name' => 'guard_role_' . uniqid(), 'guard_name' => $role_guard]);
    $role->givePermissionTo($permissions[$role_guard]);

    expect($role->hasPermission($name, $role_guard))->toBeTrue()
        ->and($role->hasPermission($name, $other_guard))->toBeFalse();
})->with([
    'web role' => ['web', 'api'],
    'api role' => ['api', 'web'],
]);

it('grants a permission inherited from an ancestor role only on the guard of the ancestor', function (string $role_guard, string $other_guard): void {
    $name = 'default.cms_tags.select';
    $permissions = guardAwarePermissionPair($name);

    $parent = Role::factory()->create(['name' => 'guard_parent_' . uniqid(), 'guard_name' => $role_guard]);
    $child = Role::factory()->create(['name' => 'guard_child_' . uniqid(), 'guard_name' => $role_guard]);
    $parent->givePermissionTo($permissions[$role_guard]);
    $child->parent_id = $parent->id;
    $child->save();

    expect($child->hasPermission($name, $role_guard))->toBeTrue()
        ->and($child->hasPermission($name, $other_guard))->toBeFalse();
})->with([
    'web role' => ['web', 'api'],
    'api role' => ['api', 'web'],
]);

it('evaluates a user on the guard asked for, through a direct grant, a role and an ancestor role', function (string $route, string $guard, string $other_guard): void {
    $name = 'default.cms_tags.select';
    $permissions = guardAwarePermissionPair($name);
    $user = User::factory()->create();

    if ($route === 'direct') {
        $user->givePermissionTo($permissions[$guard]);
    } else {
        $role = Role::factory()->create(['name' => 'guard_user_role_' . uniqid(), 'guard_name' => $guard]);
        $holder = $role;

        if ($route === 'ancestor') {
            $holder = Role::factory()->create(['name' => 'guard_user_ancestor_' . uniqid(), 'guard_name' => $guard]);
            $role->parent_id = $holder->id;
            $role->save();
        }

        $holder->givePermissionTo($permissions[$guard]);
        $user->assignRole($role);
    }

    $user = $user->fresh();

    expect($user->hasPermission($name, $guard))->toBeTrue()
        ->and($user->hasPermission($name, $other_guard))->toBeFalse();
})->with([
    'web direct' => ['direct', 'web', 'api'],
    'web role' => ['role', 'web', 'api'],
    'web ancestor' => ['ancestor', 'web', 'api'],
    'api direct' => ['direct', 'api', 'web'],
    'api role' => ['role', 'api', 'web'],
    'api ancestor' => ['ancestor', 'api', 'web'],
]);

it('uses the default guard of the application when none is given', function (): void {
    $name = 'default.cms_tags.select';
    $permissions = guardAwarePermissionPair($name);
    $role = Role::factory()->create(['name' => 'guard_default_' . uniqid(), 'guard_name' => 'api']);
    $role->givePermissionTo($permissions['api']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $user = $user->fresh();

    expect($user->hasPermission($name))->toBeFalse();

    Auth::shouldUse('api');

    expect($user->hasPermission($name))->toBeTrue()
        ->and($role->hasPermission($name))->toBeTrue();
});

it('answers false for a permission that does not exist on the guard', function (): void {
    $name = 'default.cms_tags.select';
    Permission::query()->create(['name' => $name, 'guard_name' => 'web']);

    $role = Role::factory()->create(['name' => 'guard_missing_' . uniqid(), 'guard_name' => 'api']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $user = $user->fresh();

    expect($role->hasPermission($name, 'api'))->toBeFalse()
        ->and($user->hasPermission($name, 'api'))->toBeFalse()
        ->and($user->hasPermission('default.nowhere.select', 'api'))->toBeFalse();
});

it('lets the authorization service evaluate the grants of the active guard', function (): void {
    $name = 'default.guard_gate_tags.select';
    $permissions = guardAwarePermissionPair($name);
    $api_role = Role::factory()->create(['name' => 'guard_gate_api_' . uniqid(), 'guard_name' => 'api']);
    $api_role->givePermissionTo($permissions['api']);

    $user = User::factory()->create();
    $user->assignRole($api_role);
    $user = $user->fresh();

    $request = Request::create('/');
    $request->setUserResolver(static fn (): User => $user);
    $service = new AuthorizationService(new AclResolverService());

    expect($service->checkPermission($request, 'guard_gate_tags', 'select'))->toBeFalse();

    Auth::shouldUse('api');

    expect($service->checkPermission($request, 'guard_gate_tags', 'select'))->toBeTrue();
});

it('finds the anonymous user by the configured guest name', function (): void {
    config()->set('permission.users.guest', 'visitor');

    $name = 'default.guard_anon_tags.select';
    $permissions = guardAwarePermissionPair($name);
    $api_role = Role::factory()->create(['name' => 'guard_anon_api_' . uniqid(), 'guard_name' => 'api']);
    $api_role->givePermissionTo($permissions['api']);

    $visitor = User::factory()->create(['name' => 'visitor', 'username' => 'visitor']);
    $visitor->assignRole($api_role);

    Auth::shouldUse('api');

    $request = Request::create('/');
    $service = new AuthorizationService(new AclResolverService());

    expect($service->checkPermission($request, 'guard_anon_tags', 'select'))->toBeTrue()
        ->and($request->user()?->getKey())->toBe($visitor->getKey())
        ->and(Auth::guard('api')->user()?->getKey())->toBe($visitor->getKey());
});

it('does not reuse a cached anonymous user after the configured guest name changes', function (): void {
    $first = User::factory()->create(['name' => 'first_guest', 'username' => 'first_guest']);
    $second = User::factory()->create(['name' => 'second_guest', 'username' => 'second_guest']);
    $service = new AuthorizationService(new AclResolverService());

    config()->set('permission.users.guest', 'first_guest');
    $first_request = Request::create('/');
    $service->checkPermission($first_request, 'guard_anon_tags', 'select');

    config()->set('permission.users.guest', 'second_guest');
    $second_request = Request::create('/');
    $service->checkPermission($second_request, 'guard_anon_tags', 'select');

    expect($first_request->user()?->getKey())->toBe($first->getKey())
        ->and($second_request->user()?->getKey())->toBe($second->getKey());
});

it('validates a permission name as unique per guard', function (): void {
    $name = 'default.guard_unique_tags.select';
    Permission::query()->create(['name' => $name, 'guard_name' => 'web']);

    expect(Permission::query()->create(['name' => $name, 'guard_name' => 'api'])->guard_name)->toBe('api')
        ->and(fn () => Permission::query()->create(['name' => $name, 'guard_name' => 'web']))->toThrow(ContextualValidationException::class);
});
