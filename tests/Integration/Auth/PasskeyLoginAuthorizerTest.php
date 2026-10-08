<?php

declare(strict_types=1);

use App\Models\User as AppUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Auth\PasskeyLoginAuthorizer;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\License;
use Modules\Core\Models\Pivot\ModelHasRole;
use Modules\Core\Models\Role;
use Modules\Core\Providers\CoreServiceProvider;

beforeEach(function (): void {
    config([
        'core.auth.passkeys.enabled' => true,
        'core.auth.licenses.enabled' => false,
        'core.auth.email_verification.enabled' => false,
    ]);
});

/**
 * @param  class-string<AppUser>  $class
 * @param  array<string, mixed>  $attributes
 */
function passkeyUser(string $class = AppUser::class, bool $withRole = true, array $attributes = []): AppUser
{
    $user = new $class;
    $user->forceFill(array_merge([
        'name' => 'Passkey Member',
        'email' => uniqid('pk-', true) . '@example.test',
        'username' => uniqid('pk', true),
        'password' => Hash::make('secret'),
        'license_id' => null,
        'email_verified_at' => now(),
    ], $attributes))->save();

    if ($withRole) {
        $role = Role::factory()->create(['name' => uniqid('member-', true), 'guard_name' => 'web']);
        $user->getConnection()->table((new ModelHasRole)->getTable())->insert([
            'role_id' => $role->id,
            'model_type' => $user::class,
            'model_id' => $user->getKey(),
        ]);
    }

    return $user->refresh();
}

it('accepts an active user with a role', function (): void {
    expect((new PasskeyLoginAuthorizer())->error(passkeyUser()))->toBeNull();
});

it('refuses every login while the passkeys switch is off', function (): void {
    config(['core.auth.passkeys.enabled' => false]);

    expect((new PasskeyLoginAuthorizer())->error(passkeyUser()))->toBe('Passkey login is disabled');
});

it('refuses a user without roles', function (): void {
    expect((new PasskeyLoginAuthorizer())->error(passkeyUser(withRole: false)))->toBe('User not allowed to login');
});

it('refuses an account that is not active', function (): void {
    $user = passkeyUser(attributes: ['valid_from' => now()->addDay()]);

    expect((new PasskeyLoginAuthorizer())->error($user))->toBe('Account is not active');
});

it('refuses an unverified email when verification is required', function (): void {
    $user = passkeyUser(attributes: ['email_verified_at' => null]);
    config(['core.auth.email_verification.enabled' => true]);

    expect((new PasskeyLoginAuthorizer())->error($user))->toBe('Email not verified');
});

it('refuses a user when no license is free', function (): void {
    config(['core.auth.licenses.enabled' => true]);
    License::query()->delete();

    expect((new PasskeyLoginAuthorizer())->error(passkeyUser()))->toBe('No free licenses available');
});

it('seeds the passkeys switch off by default', function (): void {
    $definition = collect(CoreDatabaseSeeder::runtimeSettingDefinitions())->firstWhere('name', 'auth.passkeys.enabled');

    expect($definition)->not->toBeNull()
        ->and($definition['value'])->toBeFalse();
});

it('adds the passkeys feature only while the switch is on', function (): void {
    $provider = new CoreServiceProvider(app());
    $method = new ReflectionMethod($provider, 'configureFortifyFeatures');

    config(['core.auth.passkeys.enabled' => false]);
    $method->invoke($provider);
    expect(config('fortify.features'))->not->toContain('passkeys');

    config(['core.auth.passkeys.enabled' => true]);
    $method->invoke($provider);
    expect(config('fortify.features'))->toContain('passkeys');
});

it('stores passkeys on the Core table', function (): void {
    $user = passkeyUser();
    $user->passkeys()->create(['name' => 'Laptop', 'credential_id' => 'credential-1', 'credential' => ['type' => 'public-key']]);

    expect(Schema::hasTable('core_passkeys'))->toBeTrue()
        ->and($user->hasPasskeysEnabled())->toBeTrue()
        ->and($user->passkeys()->first()->getTable())->toBe('core_passkeys');
});
