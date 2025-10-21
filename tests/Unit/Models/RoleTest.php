<?php

declare(strict_types=1);

use Modules\Core\Models\Role;

test('role model has correct structure', function (): void {
    $reflection = new ReflectionClass(Role::class);
    $source = file_get_contents($reflection->getFileName());
    
    // Test fillable attributes
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('\'name\'');
    expect($source)->toContain('\'guard_name\'');
    
    // Test hidden attributes
    expect($source)->toContain('protected $hidden');
    expect($source)->toContain('\'pivot\'');
});

test('role model uses correct traits', function (): void {
    $reflection = new ReflectionClass(Role::class);
    $traits = $reflection->getTraitNames();
    
    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');
    expect($traits)->toContain('Modules\\Core\\Locking\\Traits\\HasLocks');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasValidations');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasVersions');
    expect($traits)->toContain('Modules\\Core\\Helpers\\SoftDeletes');
});

test('role model has required methods', function (): void {
    $reflection = new ReflectionClass(Role::class);
    
    expect($reflection->hasMethod('users'))->toBeTrue();
    expect($reflection->hasMethod('permissions'))->toBeTrue();
    expect($reflection->hasMethod('getRules'))->toBeTrue();
});

test('role model has correct relationships', function (): void {
    $reflection = new ReflectionClass(Role::class);
    
    // Test users relationship
    $method = $reflection->getMethod('users');
    expect($method->getReturnType()->getName())->toBe('Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany');
    
    // Test permissions relationship
    $method = $reflection->getMethod('permissions');
    expect($method->getReturnType()->getName())->toBe('Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany');
});

test('role model has correct method signatures', function (): void {
    $reflection = new ReflectionClass(Role::class);
    
    // Test getRules method
    $method = $reflection->getMethod('getRules');
    expect($method->getReturnType()->getName())->toBe('array');
});