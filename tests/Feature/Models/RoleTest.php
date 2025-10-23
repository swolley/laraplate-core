<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->role = Role::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->role)->toBeInstanceOf(Role::class);
    expect($this->role->id)->not->toBeNull();
});

it('has fillable attributes', function (): void {
    $roleData = [
        'name' => 'test-role',
        'guard_name' => 'web',
        'description' => 'Test role description',
    ];

    $role = Role::create($roleData);

    expectModelAttributes($role, [
        'name' => 'test-role',
        'guard_name' => 'web',
        'description' => 'Test role description',
    ]);
});

it('has hidden attributes', function (): void {
    $role = Role::factory()->create();
    $roleArray = $role->toArray();

    expect($roleArray)->not->toHaveKey('parent_id');
    expect($roleArray)->not->toHaveKey('pivot');
});

it('belongs to many users', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $this->role->users()->attach([$user1->id, $user2->id]);

    expect($this->role->users)->toHaveCount(2);
    expect($this->role->users->pluck('id')->toArray())->toContain($user1->id, $user2->id);
});

it('belongs to many permissions', function (): void {
    $permission1 = Permission::factory()->create(['name' => 'users.create']);
    $permission2 = Permission::factory()->create(['name' => 'users.update']);

    $this->role->permissions()->attach([$permission1->id, $permission2->id]);

    expect($this->role->permissions)->toHaveCount(2);
    expect($this->role->permissions->pluck('name')->toArray())->toContain('users.create', 'users.update');
});

it('has recursive relationships for parent-child roles', function (): void {
    $parentRole = Role::factory()->create(['name' => 'parent-role']);
    $childRole = Role::factory()->create(['name' => 'child-role']);

    $childRole->parent_id = $parentRole->id;
    $childRole->save();

    expect($childRole->parent)->toBeInstanceOf(Role::class);
    expect($childRole->parent->id)->toBe($parentRole->id);
    expect($parentRole->children)->toHaveCount(1);
    expect($parentRole->children->first()->id)->toBe($childRole->id);
});

it('can get all permissions including from ancestors', function (): void {
    $parentRole = Role::factory()->create(['name' => 'parent-role']);
    $childRole = Role::factory()->create(['name' => 'child-role']);

    $parentPermission = Permission::factory()->create(['name' => 'parent.permission']);
    $childPermission = Permission::factory()->create(['name' => 'child.permission']);

    $parentRole->permissions()->attach($parentPermission);
    $childRole->permissions()->attach($childPermission);

    $childRole->parent_id = $parentRole->id;
    $childRole->save();

    $allPermissions = $childRole->getAllPermissions();

    expect($allPermissions->pluck('name')->toArray())->toContain('parent.permission', 'child.permission');
});

it('can check if role has specific permission', function (): void {
    $permission = Permission::factory()->create(['name' => 'users.create']);
    $this->role->permissions()->attach($permission);

    expect($this->role->hasPermission('users.create'))->toBeTrue();
    expect($this->role->hasPermission('users.delete'))->toBeFalse();
});

it('can check permission from parent role', function (): void {
    $parentRole = Role::factory()->create(['name' => 'parent-role']);
    $childRole = Role::factory()->create(['name' => 'child-role']);

    $permission = Permission::factory()->create(['name' => 'users.create']);
    $parentRole->permissions()->attach($permission);

    $childRole->parent_id = $parentRole->id;
    $childRole->save();

    expect($childRole->hasPermission('users.create'))->toBeTrue();
});

it('has validation rules for creation', function (): void {
    $rules = $this->role->getRules();

    expect($rules['create']['name'])->toContain('required', 'string', 'max:255', 'unique:roles');
    expect($rules['create']['guard_name'])->toContain('string', 'max:255');
    expect($rules['create']['description'])->toContain('string', 'max:255', 'nullable');
});

it('has validation rules for update', function (): void {
    $rules = $this->role->getRules();

    expect($rules['update']['name'])->toContain('sometimes', 'string', 'max:255');
    expect($rules['update']['name'])->toContain('unique:roles');
});

it('validates unique name on creation', function (): void {
    Role::factory()->create(['name' => 'existing-role']);

    expect(fn () => Role::create(['name' => 'existing-role', 'guard_name' => 'web']))
        ->toThrow(ValidationException::class);
});

it('validates unique name on update ignoring self', function (): void {
    $role = Role::factory()->create(['name' => 'test-role']);

    // Should not throw when updating with same name
    expect(fn () => $role->update(['name' => 'test-role']))
        ->not->toThrow(ValidationException::class);
});

it('has soft deletes trait', function (): void {
    $this->role->delete();

    expect($this->role->trashed())->toBeTrue();
    expect(Role::withTrashed()->find($this->role->id))->not->toBeNull();
});

it('has versions trait', function (): void {
    expect($this->role)->toHaveMethod('versions');
    expect($this->role)->toHaveMethod('createVersion');
});

it('has locks trait', function (): void {
    expect($this->role)->toHaveMethod('lock');
    expect($this->role)->toHaveMethod('unlock');
});

it('has validations trait', function (): void {
    expect($this->role)->toHaveMethod('getRules');
});

it('has cache trait', function (): void {
    expect($this->role)->toHaveMethod('cache');
    expect($this->role)->toHaveMethod('forgetCache');
});

it('has proper casts for dates', function (): void {
    $role = Role::factory()->create();

    expect($role->created_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    expect($role->updated_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('can be created with specific attributes', function (): void {
    $roleData = [
        'name' => 'custom-role',
        'guard_name' => 'api',
        'description' => 'Custom role for API access',
    ];

    $role = Role::create($roleData);

    expectModelAttributes($role, [
        'name' => 'custom-role',
        'guard_name' => 'api',
        'description' => 'Custom role for API access',
    ]);
});

it('can be found by name', function (): void {
    $role = Role::factory()->create(['name' => 'unique-role']);

    $foundRole = Role::where('name', 'unique-role')->first();

    expect($foundRole->id)->toBe($role->id);
});

it('can be found by guard name', function (): void {
    $role = Role::factory()->create(['guard_name' => 'api']);

    $foundRole = Role::where('guard_name', 'api')->first();

    expect($foundRole->id)->toBe($role->id);
});
