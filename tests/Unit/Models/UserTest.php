<?php

declare(strict_types=1);

use Modules\Core\Models\User;

test('user model has correct structure', function (): void {
    $reflection = new ReflectionClass(User::class);
    $source = file_get_contents($reflection->getFileName());

    // Test fillable attributes
    expect($source)->toContain('protected $fillable');
    expect($source)->toContain('\'name\'');
    expect($source)->toContain('\'email\'');
    expect($source)->toContain('\'username\'');
    expect($source)->toContain('\'password\'');
    expect($source)->toContain('\'lang\'');

    // Test hidden attributes
    expect($source)->toContain('protected $hidden');
    expect($source)->toContain('\'password\'');
    expect($source)->toContain('\'remember_token\'');
    expect($source)->toContain('\'pivot\'');
    expect($source)->toContain('\'license_id\'');
    expect($source)->toContain('\'two_factor_secret\'');
    expect($source)->toContain('\'two_factor_recovery_codes\'');
    expect($source)->toContain('\'last_login_at\'');

    // Test casts method
    expect($source)->toContain('protected function casts()');
    expect($source)->toContain('email_verified_at');
    expect($source)->toContain('password');
    expect($source)->toContain('created_at');
    expect($source)->toContain('updated_at');
});

test('user model uses correct traits', function (): void {
    $reflection = new ReflectionClass(User::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Approval\\Traits\\ApprovesChanges');
    expect($traits)->toContain('Illuminate\\Database\\Eloquent\\Factories\\HasFactory');
    expect($traits)->toContain('Modules\\Core\\Locking\\Traits\\HasLocks');
    expect($traits)->toContain('Spatie\\Permission\\Traits\\HasRoles');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasValidations');
    expect($traits)->toContain('Modules\\Core\\Helpers\\HasVersions');
    expect($traits)->toContain('Illuminate\\Notifications\\Notifiable');
    expect($traits)->toContain('Modules\\Core\\Helpers\\SoftDeletes');
    expect($traits)->toContain('Laravel\\Fortify\\TwoFactorAuthenticatable');
});

test('user model has required methods', function (): void {
    $reflection = new ReflectionClass(User::class);

    expect($reflection->hasMethod('license'))->toBeTrue();
    expect($reflection->hasMethod('isSuperAdmin'))->toBeTrue();
    expect($reflection->hasMethod('isAdmin'))->toBeTrue();
    expect($reflection->hasMethod('canImpersonate'))->toBeTrue();
    expect($reflection->hasMethod('canBeImpersonated'))->toBeTrue();
    expect($reflection->hasMethod('canAccessPanel'))->toBeTrue();
    expect($reflection->hasMethod('isGuest'))->toBeTrue();
    expect($reflection->hasMethod('getImpersonator'))->toBeTrue();
    expect($reflection->hasMethod('getRules'))->toBeTrue();
});

test('user model implements correct interfaces', function (): void {
    $reflection = new ReflectionClass(User::class);

    expect($reflection->implementsInterface('Filament\\Models\\Contracts\\FilamentUser'))->toBeTrue();
    expect($reflection->implementsInterface('Illuminate\\Contracts\\Auth\\MustVerifyEmail'))->toBeTrue();
});

test('user model has correct method signatures', function (): void {
    $reflection = new ReflectionClass(User::class);

    // Test isSuperAdmin method
    $method = $reflection->getMethod('isSuperAdmin');
    expect($method->getReturnType()->getName())->toBe('bool');

    // Test isAdmin method
    $method = $reflection->getMethod('isAdmin');
    expect($method->getReturnType()->getName())->toBe('bool');

    // Test canImpersonate method
    $method = $reflection->getMethod('canImpersonate');
    expect($method->getReturnType()->getName())->toBe('bool');

    // Test canBeImpersonated method
    $method = $reflection->getMethod('canBeImpersonated');
    expect($method->getReturnType()->getName())->toBe('bool');

    // Test canAccessPanel method
    $method = $reflection->getMethod('canAccessPanel');
    expect($method->getParameters())->toHaveCount(1);
    expect($method->getParameters()[0]->getType()->getName())->toBe('Filament\\Panel');
    expect($method->getReturnType()->getName())->toBe('bool');

    // Test isGuest method
    $method = $reflection->getMethod('isGuest');
    expect($method->getReturnType()->getName())->toBe('bool');

    // Test getImpersonator method
    $method = $reflection->getMethod('getImpersonator');
    expect($method->getReturnType()->getName())->toBe('self');

    // Test getRules method
    $method = $reflection->getMethod('getRules');
    expect($method->getReturnType()->getName())->toBe('array');
});

test('user model has license relationship', function (): void {
    $reflection = new ReflectionClass(User::class);
    expect($reflection->hasMethod('license'))->toBeTrue();

    $method = $reflection->getMethod('license');
    expect($method->getReturnType()->getName())->toBe('Illuminate\\Database\\Eloquent\\Relations\\BelongsTo');
});
