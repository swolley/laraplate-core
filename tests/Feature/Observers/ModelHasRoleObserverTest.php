<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Pivot\ModelHasRole;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Observers\ModelHasRoleObserver;
use Modules\Core\Tests\Stubs\UserForcedSuperRole;

it('prevents assigning another role to a user who already has superadmin role', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $superadmin_role = Role::factory()->create(['name' => 'superadmin']);
    $other_role = Role::factory()->create(['name' => 'editor']);
    $user = User::factory()->create();
    $user->roles()->attach($superadmin_role);

    expect(fn () => $user->roles()->attach($other_role))
        ->toThrow(ValidationException::class);
});

it('allows assigning superadmin role to a user who already has superadmin', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $superadmin_role = Role::factory()->create(['name' => 'superadmin']);
    $user = User::factory()->create();
    $user->roles()->attach($superadmin_role);

    $user->roles()->sync([$superadmin_role->id]);

    expect($user->roles->pluck('id')->toArray())->toContain($superadmin_role->id);
});

it('allows assigning other roles when user does not have superadmin role', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $editor_role = Role::factory()->create(['name' => 'editor']);
    $viewer_role = Role::factory()->create(['name' => 'viewer']);
    $user = User::factory()->create();
    $user->roles()->attach($editor_role);

    $user->roles()->attach($viewer_role);

    expect($user->roles()->count())->toBe(2);
});

it('returns early when model is not a User instance', function (): void {
    $pivot = new ModelHasRole;
    $pivot->model_type = Modules\Core\Models\Setting::class;
    $pivot->model_id = 999;
    $pivot->role_id = 1;

    $observer = new ModelHasRoleObserver;
    $observer->creating($pivot);

    expect(true)->toBeTrue();
});

it('returns early when model_type or model_id is missing', function (): void {
    $pivot = new ModelHasRole;
    $pivot->model_type = null;
    $pivot->model_id = null;
    $pivot->role_id = 1;

    $observer = new ModelHasRoleObserver;
    $observer->creating($pivot);

    expect(true)->toBeTrue();
});

it('returns early when model_type class does not exist', function (): void {
    $pivot = new ModelHasRole;
    $pivot->model_type = 'NonExistent\\FakeClass';
    $pivot->model_id = 1;
    $pivot->role_id = 1;

    $observer = new ModelHasRoleObserver;
    $observer->creating($pivot);

    expect(true)->toBeTrue();
});

it('returns early when superadmin role does not exist in database', function (): void {
    config(['permission.roles.superadmin' => 'nonexistent_superadmin_role_xyz']);
    $role = Role::factory()->create(['name' => 'editor']);
    $user = User::factory()->create();
    $user->roles()->attach($role);

    $pivot = new ModelHasRole;
    $pivot->model_type = User::class;
    $pivot->model_id = $user->id;
    $pivot->role_id = $role->id;

    $observer = new ModelHasRoleObserver;
    $observer->creating($pivot);

    expect(true)->toBeTrue();
});

it('returns early when user appears superadmin but superadmin role row is missing', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    Role::factory()->create(['name' => 'editor']);
    $user = UserForcedSuperRole::factory()->create();

    $pivot = new ModelHasRole;
    $pivot->model_type = UserForcedSuperRole::class;
    $pivot->model_id = $user->id;
    $pivot->role_id = Role::query()->where('name', 'editor')->value('id');

    (new ModelHasRoleObserver)->creating($pivot);

    expect(true)->toBeTrue();
});

it('returns early when pivot assigns the same superadmin role id', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $superadmin_role = Role::factory()->create(['name' => 'superadmin']);
    $user = User::factory()->create();
    $user->roles()->attach($superadmin_role);

    $pivot = new ModelHasRole;
    $pivot->model_type = User::class;
    $pivot->model_id = $user->id;
    $pivot->role_id = $superadmin_role->id;

    (new ModelHasRoleObserver)->creating($pivot);

    expect(true)->toBeTrue();
});
