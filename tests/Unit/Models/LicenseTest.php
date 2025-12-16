<?php

declare(strict_types=1);

use Modules\Core\Models\License;

it('license model has correct structure', function (): void {
    $reflection = new ReflectionClass(License::class);
    $source = file_get_contents($reflection->getFileName());

    // Test fillable attributes
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('[]');
});

it('license model uses correct traits', function (): void {
    $reflection = new ReflectionClass(License::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');
    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Concerns\\HasUuids');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasValidations');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasValidity');
});

it('license model has required methods', function (): void {
    $reflection = new ReflectionClass(License::class);

    expect($reflection->hasMethod('user'))->toBeTrue();
    expect($reflection->hasMethod('getRules'))->toBeTrue();
});

it('license model has correct relationships', function (): void {
    $reflection = new ReflectionClass(License::class);

    // Test user relationship
    $method = $reflection->getMethod('user');
    expect($method->getReturnType()->getName())->toBe('Illuminate\\Database\\Eloquent\\Relations\\HasOne');
});

it('license model has correct method signatures', function (): void {
    $reflection = new ReflectionClass(License::class);

    // Test getRules method
    $method = $reflection->getMethod('getRules');
    expect($method->getReturnType()->getName())->toBe('array');
});
