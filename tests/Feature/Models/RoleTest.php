<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    /** @var TestCase $this */
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

    expect($role->name)->toBe('test-role');
    expect($role->guard_name)->toBe('web');
    expect($role->description)->toBe('Test role description');
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
    $permission1 = Permission::create(['name' => 'default.users_table.insert']);
    $permission2 = Permission::create(['name' => 'default.users_table.update']);

    $this->role->permissions()->attach([$permission1->id, $permission2->id]);

    expect($this->role->permissions)->toHaveCount(2);
    expect($this->role->permissions->pluck('name')->toArray())->toContain('default.users_table.insert', 'default.users_table.update');
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

    $parentPermission = Permission::create(['name' => 'default.parent_table.select']);
    $childPermission = Permission::create(['name' => 'default.child_table.select']);

    $parentRole->permissions()->attach($parentPermission);
    $childRole->permissions()->attach($childPermission);

    $childRole->parent_id = $parentRole->id;
    $childRole->save();

    $allPermissions = $childRole->getAllPermissions();

    expect($allPermissions->pluck('name')->toArray())->toContain('default.parent_table.select', 'default.child_table.select');
});

it('can check if role has specific permission', function (): void {
    $permission = Permission::create(['name' => 'default.users_table.insert']);
    $this->role->permissions()->attach($permission);
    $this->role->refresh(); // Refresh to ensure permissions are loaded

    expect($this->role->hasPermission('default.users_table.insert'))->toBeTrue();
    expect($this->role->hasPermission('default.users_table.delete'))->toBeFalse();
});

it('can check permission from parent role', function (): void {
    $parentRole = Role::factory()->create(['name' => 'parent-role']);
    $childRole = Role::factory()->create(['name' => 'child-role']);

    $permission = Permission::create(['name' => 'default.users_table.insert']);
    $parentRole->permissions()->attach($permission);

    $childRole->parent_id = $parentRole->id;
    $childRole->save();

    expect($childRole->hasPermission('default.users_table.insert'))->toBeTrue();
});

it('has validation rules for creation', function (): void {
    $rules = $this->role->getRules();

    expect(in_array('required', $rules['create']['name'], true))->toBeTrue();
    expect(in_array('string', $rules['create']['name'], true))->toBeTrue();
    expect(in_array('max:255', $rules['create']['name'], true))->toBeTrue();
    // Check that unique rule exists (it's a Rule object, not a string)
    $hasUniqueRule = false;
    foreach ($rules['create']['name'] as $rule) {
        if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
            $hasUniqueRule = true;
            break;
        }
    }
    expect($hasUniqueRule)->toBeTrue();
    // guard_name is in DEFAULT_RULE (always), not in create
    expect(in_array('string', $rules['always']['guard_name'] ?? [], true))->toBeTrue();
    expect(in_array('max:255', $rules['always']['guard_name'] ?? [], true))->toBeTrue();
    expect(in_array('string', $rules['always']['description'] ?? [], true))->toBeTrue();
    expect(in_array('nullable', $rules['always']['description'] ?? [], true))->toBeTrue();
});

it('has validation rules for update', function (): void {
    $rules = $this->role->getRules();

    expect(in_array('sometimes', $rules['update']['name'], true))->toBeTrue();
    expect(in_array('string', $rules['update']['name'], true))->toBeTrue();
    expect(in_array('max:255', $rules['update']['name'], true))->toBeTrue();
    // Check that unique rule exists (it's a Rule object, not a string)
    $hasUniqueRule = false;
    foreach ($rules['update']['name'] as $rule) {
        if ($rule instanceof \Illuminate\Validation\Rules\Unique) {
            $hasUniqueRule = true;
            break;
        }
    }
    expect($hasUniqueRule)->toBeTrue();
    // guard_name is in DEFAULT_RULE, not in update
    expect(in_array('string', $rules['always']['guard_name'] ?? [], true))->toBeTrue();
});

it('validates unique name on creation', function (): void {
    Role::factory()->create(['name' => 'existing-role']);

    // Laravel validation throws ValidationException when using Rule::unique()
    expect(fn () => Role::create(['name' => 'existing-role', 'guard_name' => 'web']))
        ->toThrow(ValidationException::class);
});
<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
run_terminal_cmd

it('validates unique name on update ignoring self', function (): void {
    $role = Role::factory()->create(['name' => 'test-role']);

    // Should not throw when updating with same name
    $result = $role->update(['name' => 'test-role']);
    expect($result)->toBeTrue();
});

it('has soft deletes trait', function (): void {
    $this->role->delete();

    expect($this->role->trashed())->toBeTrue();
    expect(Role::withTrashed()->find($this->role->id))->not->toBeNull();
});

it('has versions trait', function (): void {
    /** @var TestCase $this */
    expect(method_exists($this->role, 'versions'))->toBeTrue();
    expect(method_exists($this->role, 'createVersion'))->toBeTrue();
});

it('has locks trait', function (): void {
    /** @var TestCase $this */
    expect(method_exists($this->role, 'lock'))->toBeTrue();
    expect(method_exists($this->role, 'unlock'))->toBeTrue();
});

it('has validations trait', function (): void {
    /** @var TestCase $this */
    expect(method_exists($this->role, 'getRules'))->toBeTrue();
});

it('has cache trait', function (): void {
    /** @var TestCase $this */
    expect(method_exists($this->role, 'getCacheKey'))->toBeTrue();
    expect(method_exists($this->role, 'usesCache'))->toBeTrue();
    expect(method_exists($this->role, 'invalidateCache'))->toBeTrue();
});

it('has proper casts for dates', function (): void {
    $role = Role::factory()->create();

    expect($role->created_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
    expect($role->updated_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

it('can be created with specific attributes', function (): void {
    $roleData = [
        'name' => 'custom-role',
        'guard_name' => 'api',
        'description' => 'Custom role for API access',
    ];

    $role = Role::create($roleData);

    expect($role->name)->toBe('custom-role');
    expect($role->guard_name)->toBe('api');
    expect($role->description)->toBe('Custom role for API access');
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
