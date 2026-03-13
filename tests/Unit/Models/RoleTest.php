<?php

declare(strict_types=1);

use Modules\Core\Models\Role;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('role model has correct structure', function (): void {
    $reflection = new ReflectionClass(Role::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('protected $fillable')
        ->and($source)->toContain('\'name\'')
        ->and($source)->toContain('\'guard_name\'')
        ->and($source)->toContain('protected $hidden')
        ->and($source)->toContain('\'pivot\'');
});

it('role model uses correct traits', function (): void {
    $reflection = new ReflectionClass(Role::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Factories\\HasFactory')
        ->and($traits)->toContain('Modules\\Core\\Locking\\Traits\\HasLocks')
        ->and($traits)->toContain('Modules\\Core\\Helpers\\HasValidations')
        ->and($traits)->toContain('Modules\\Core\\Helpers\\HasVersions')
        ->and($traits)->toContain('Modules\\Core\\Helpers\\SoftDeletes');
});

it('role model has required methods', function (): void {
    $reflection = new ReflectionClass(Role::class);

    expect($reflection->hasMethod('users'))->toBeTrue()
        ->and($reflection->hasMethod('permissions'))->toBeTrue()
        ->and($reflection->hasMethod('getRules'))->toBeTrue();
});

it('getRules returns update rule with unique constraint and ignore', function (): void {
    $role = Role::factory()->create(['name' => 'test_role']);
    $rules = $role->getRules();

    expect($rules)->toHaveKey('update')
        ->and($rules['update'])->toHaveKey('name');

    $has_unique = false;

    foreach ($rules['update']['name'] as $rule) {
        if ($rule instanceof Illuminate\Validation\Rules\Unique) {
            $has_unique = true;
        }
    }
    expect($has_unique)->toBeTrue();
});

it('isSuperAdminRole returns true for superadmin role name', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $role = Role::factory()->create(['name' => 'superadmin']);

    expect($role->isSuperAdminRole())->toBeTrue();
});

it('isSuperAdminRole returns false for regular role', function (): void {
    config(['permission.roles.superadmin' => 'superadmin']);
    $role = Role::factory()->create(['name' => 'editor']);

    expect($role->isSuperAdminRole())->toBeFalse();
});

it('rejectAnyPermissionForSuperAdmin throws with non-empty permissions', function (): void {
    $role = Role::factory()->create(['name' => 'superadmin']);
    config(['permission.roles.superadmin' => 'superadmin']);

    $method = new ReflectionMethod(Role::class, 'rejectAnyPermissionForSuperAdmin');

    expect(fn () => $method->invoke($role, ['some_permission']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('rejectAnyPermissionForSuperAdmin does nothing with empty permissions', function (): void {
    $role = Role::factory()->create(['name' => 'superadmin']);

    $method = new ReflectionMethod(Role::class, 'rejectAnyPermissionForSuperAdmin');

    $method->invoke($role, []);
    expect(true)->toBeTrue();
});

it('rejectAnyPermissionForSuperAdmin does nothing when all permissions are empty', function (): void {
    $role = Role::factory()->create(['name' => 'superadmin']);

    $method = new ReflectionMethod(Role::class, 'rejectAnyPermissionForSuperAdmin');

    $method->invoke($role, [null, '', 0]);
    expect(true)->toBeTrue();
});

it('casts returns expected keys', function (): void {
    $role = new Role;
    $casts = (new ReflectionMethod(Role::class, 'casts'))->invoke($role);

    expect($casts)->toHaveKey('created_at')
        ->and($casts)->toHaveKey('updated_at')
        ->and($casts['created_at'])->toBe('immutable_datetime');
});
