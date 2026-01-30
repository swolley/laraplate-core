<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
