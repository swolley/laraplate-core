<?php

declare(strict_types=1);

use Modules\Core\Models\Permission;

test('permission model has correct structure', function (): void {
    $reflection = new ReflectionClass(Permission::class);
    $source = file_get_contents($reflection->getFileName());

    // Test fillable attributes
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('\'name\'');
    expect($source)->toContain('\'guard_name\'');

    // Test hidden attributes
    expect($source)->toContain('protected $hidden');
    expect($source)->toContain('\'pivot\'');
});

test('permission model uses correct traits', function (): void {
    $reflection = new ReflectionClass(Permission::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');
    expect($traits)->toContain('Modules\\Core\\Cache\\HasCache');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasValidations');
});

test('permission model has required methods', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    expect($reflection->hasMethod('users'))->toBeTrue();
    expect($reflection->hasMethod('roles'))->toBeTrue();
    expect($reflection->hasMethod('getRules'))->toBeTrue();
});

test('permission model has correct relationships', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    // Test users relationship
    $method = $reflection->getMethod('users');
    expect($method->getReturnType()->getName())->toBe('Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany');

    // Test roles relationship
    $method = $reflection->getMethod('roles');
    expect($method->getReturnType()->getName())->toBe('Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany');
});

test('permission model has correct method signatures', function (): void {
    $reflection = new ReflectionClass(Permission::class);

    // Test getRules method
    $method = $reflection->getMethod('getRules');
    expect($method->getReturnType()->getName())->toBe('array');
});
