<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->permission = Permission::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->permission)->toBeInstanceOf(Permission::class);
    expect($this->permission->id)->not->toBeNull();
});

it('has fillable attributes', function (): void {
    $permissionData = [
        'name' => 'users.create',
        'guard_name' => 'web',
    ];

    $permission = Permission::create($permissionData);

    expectModelAttributes($permission, [
        'name' => 'users.create',
        'guard_name' => 'web',
    ]);
});

it('has guarded attributes', function (): void {
    $permission = Permission::factory()->create();
    $permissionArray = $permission->toArray();

    expect($permissionArray)->not->toHaveKey('connection_name');
    expect($permissionArray)->not->toHaveKey('table_name');
});

it('has hidden attributes', function (): void {
    $permission = Permission::factory()->create();
    $permissionArray = $permission->toArray();

    expect($permissionArray)->not->toHaveKey('pivot');
});

it('has default guard name', function (): void {
    $permission = Permission::factory()->create(['name' => 'test.permission']);

    expect($permission->guard_name)->toBe('web');
});

it('has many acls relationship', function (): void {
    $acl1 = ACL::factory()->create();
    $acl2 = ACL::factory()->create();

    $this->permission->acls()->saveMany([$acl1, $acl2]);

    expect($this->permission->acls)->toHaveCount(2);
    expect($this->permission->acls->pluck('id')->toArray())->toContain($acl1->id, $acl2->id);
});

it('has action attribute from name', function (): void {
    $permission = Permission::factory()->create(['name' => 'users.create']);

    expect($permission->action)->toBe('create');
});

it('has null action when name is null', function (): void {
    $permission = new Permission(['name' => null]);

    expect($permission->action)->toBeNull();
});

it('extracts action from permission name', function (): void {
    $permission = Permission::factory()->create(['name' => 'posts.update']);

    expect($permission->action)->toBe('update');
});

it('has validation rules for creation', function (): void {
    $rules = $this->permission->getRules();

    expect($rules['create']['name'])->toContain('required', 'string', 'max:255', 'regex:/^\\w+\\.\\w+\\.\\w+$/', 'unique:permissions,name');
    expect($rules['create']['guard_name'])->toContain('string', 'max:255');
});

it('has validation rules for update', function (): void {
    $rules = $this->permission->getRules();

    expect($rules['update']['name'])->toContain('sometimes', 'string', 'max:255', 'regex:/^\\w+\\.\\w+\\.\\w+$/');
    expect($rules['update']['name'])->toContain('unique:permissions,name,' . $this->permission->id);
});

it('validates name format with regex', function (): void {
    expect(fn () => Permission::create(['name' => 'invalid-name', 'guard_name' => 'web']))
        ->toThrow(ValidationException::class);

    expect(fn () => Permission::create(['name' => 'users.create', 'guard_name' => 'web']))
        ->not->toThrow(ValidationException::class);
});

it('validates unique name on creation', function (): void {
    Permission::factory()->create(['name' => 'users.create']);

    expect(fn () => Permission::create(['name' => 'users.create', 'guard_name' => 'web']))
        ->toThrow(ValidationException::class);
});

it('validates unique name on update ignoring self', function (): void {
    $permission = Permission::factory()->create(['name' => 'users.create']);

    // Should not throw when updating with same name
    expect(fn () => $permission->update(['name' => 'users.create']))
        ->not->toThrow(ValidationException::class);
});

it('has validations trait', function (): void {
    expect($this->permission)->toHaveMethod('getRules');
});

it('has cache trait', function (): void {
    expect($this->permission)->toHaveMethod('cache');
    expect($this->permission)->toHaveMethod('forgetCache');
});

it('can be created with specific attributes', function (): void {
    $permissionData = [
        'name' => 'posts.delete',
        'guard_name' => 'api',
    ];

    $permission = Permission::create($permissionData);

    expectModelAttributes($permission, [
        'name' => 'posts.delete',
        'guard_name' => 'api',
    ]);
});

it('can be found by name', function (): void {
    $permission = Permission::factory()->create(['name' => 'unique.permission']);

    $foundPermission = Permission::where('name', 'unique.permission')->first();

    expect($foundPermission->id)->toBe($permission->id);
});

it('can be found by guard name', function (): void {
    $permission = Permission::factory()->create(['guard_name' => 'api']);

    $foundPermission = Permission::where('guard_name', 'api')->first();

    expect($foundPermission->id)->toBe($permission->id);
});

it('has proper action extraction for different formats', function (): void {
    $permissions = [
        'users.create' => 'create',
        'posts.update' => 'update',
        'comments.delete' => 'delete',
        'files.view' => 'view',
    ];

    foreach ($permissions as $name => $expectedAction) {
        $permission = Permission::factory()->create(['name' => $name]);
        expect($permission->action)->toBe($expectedAction);
    }
});

it('can be created with factory using different names', function (): void {
    $permission = Permission::factory()->create(['name' => 'custom.action']);

    expect($permission->name)->toBe('custom.action');
    expect($permission->action)->toBe('action');
});
