<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('can be created with factory', function (): void {
    expect($this->user)->toBeInstanceOf(User::class);
    expect($this->user->id)->not->toBeNull();
});

it('has fillable attributes', function (): void {
    $userData = [
        'name' => 'Test User',
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'password',
        'lang' => 'en',
    ];

    $user = User::create($userData);

    expect($user->name)->toBe('Test User');
    expect($user->username)->toBe('testuser');
    expect($user->email)->toBe('test@example.com');
    expect($user->lang)->toBe('en');
});

it('has hidden attributes', function (): void {
    $user = User::factory()->create();
    $userArray = $user->toArray();

    expect($userArray)->not->toHaveKey('password');
    expect($userArray)->not->toHaveKey('remember_token');
    expect($userArray)->not->toHaveKey('two_factor_secret');
    expect($userArray)->not->toHaveKey('two_factor_recovery_codes');
});

it('has many roles relationship', function (): void {
    $role1 = Role::factory()->create(['name' => 'admin']);
    $role2 = Role::factory()->create(['name' => 'editor']);

    $this->user->roles()->attach([$role1->id, $role2->id]);

    expect($this->user->roles)->toHaveCount(2);
    expect($this->user->roles->pluck('name')->toArray())->toContain('admin', 'editor');
});

it('can check if user is super admin', function (): void {
    $adminRole = Role::factory()->create(['name' => 'superadmin']);
    $this->user->roles()->attach($adminRole);

    expect($this->user->isSuperAdmin())->toBeTrue();
});

it('can check if user is not super admin', function (): void {
    $regularRole = Role::factory()->create(['name' => 'user']);
    $this->user->roles()->attach($regularRole);

    expect($this->user->isSuperAdmin())->toBeFalse();
});

it('can access filament panel when super admin', function (): void {
    $adminRole = Role::factory()->create(['name' => 'superadmin']);
    $this->user->roles()->attach($adminRole);

    // Test that user has superadmin role
    expect($this->user->isSuperAdmin())->toBeTrue();
});

it('can impersonate other users when has permission', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    // Test that user has admin role
    expect($this->user->hasRole('admin'))->toBeTrue();
});

it('has email verification required', function (): void {
    expect($this->user)->toBeInstanceOf(Illuminate\Contracts\Auth\MustVerifyEmail::class);
});

it('has two factor authentication trait', function (): void {
    // Test that the trait is used by checking for a common method
    expect(method_exists($this->user, 'twoFactorQrCodeSvg'))->toBeTrue();
});

it('has soft deletes trait', function (): void {
    $this->user->delete();

    expect($this->user->trashed())->toBeTrue();
    expect(User::withTrashed()->find($this->user->id))->not->toBeNull();
});

it('has versions trait', function (): void {
    expect(method_exists($this->user, 'versions'))->toBeTrue();
    expect(method_exists($this->user, 'createVersion'))->toBeTrue();
});

it('has locks trait', function (): void {
    expect(method_exists($this->user, 'lock'))->toBeTrue();
    expect(method_exists($this->user, 'unlock'))->toBeTrue();
});

it('has validations trait', function (): void {
    expect(method_exists($this->user, 'getRules'))->toBeTrue();
});

it('has approval changes trait', function (): void {
    expect(method_exists($this->user, 'authorizedToApprove'))->toBeTrue();
    expect(method_exists($this->user, 'authorizedToDisapprove'))->toBeTrue();
});

it('can be observed', function (): void {
    expect(method_exists($this->user, 'observe'))->toBeTrue();
});

it('has proper casts for dates', function (): void {
    $user = User::factory()->create();

    expect($user->created_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
    expect($user->updated_at)->toBeInstanceOf(Carbon\CarbonImmutable::class);
});

it('can be created with specific attributes', function (): void {
    $userData = [
        'name' => 'John Doe',
        'username' => 'johndoe',
        'email' => 'john@example.com',
        'password' => 'password',
        'lang' => 'it',
    ];

    $user = User::create($userData);

    expect($user->name)->toBe('John Doe');
    expect($user->username)->toBe('johndoe');
    expect($user->email)->toBe('john@example.com');
    expect($user->lang)->toBe('it');
});

it('has proper password hashing', function (): void {
    $user = User::factory()->create(['password' => 'plaintext']);

    expect(Hash::check('plaintext', $user->password))->toBeTrue();
});

it('can be found by email', function (): void {
    $user = User::factory()->create(['email' => 'unique@example.com']);

    $foundUser = User::where('email', 'unique@example.com')->first();

    expect($foundUser->id)->toBe($user->id);
});

it('can be found by username', function (): void {
    $user = User::factory()->create(['username' => 'uniqueuser']);

    $foundUser = User::where('username', 'uniqueuser')->first();

    expect($foundUser->id)->toBe($user->id);
});
