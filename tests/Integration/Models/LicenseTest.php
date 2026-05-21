<?php

declare(strict_types=1);

use Modules\Core\Models\License;

it('license model has correct structure', function (): void {
    $reflection = new ReflectionClass(License::class);
    $source = file_get_contents($reflection->getFileName());

    // Test fillable attributes (uuid; HasValidity appends valid_from / valid_to at runtime)
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('\'uuid\'');
});

it('license model uses correct traits', function (): void {
    $traits = class_uses_recursive(License::class);

    expect($traits)->toHaveKey('Illuminate\\Database\\Eloquent\\Factories\\HasFactory')
        ->and($traits)->toHaveKey('Modules\\Core\\Helpers\\HasValidations')
        ->and($traits)->toHaveKey('Modules\\Core\\Helpers\\HasValidity');
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
