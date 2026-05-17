<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Auth\Providers\FortifyCredentialsProvider;
use Modules\Core\Models\Role;

/**
 * @return array{success: bool, user: User|null, error: string|null, license: mixed}
 */
function authenticateUserWithCredentials(User $user, string $password = 'Password1!'): array
{
    $provider = app(FortifyCredentialsProvider::class);

    return $provider->authenticate(Request::create('/login', 'POST', [
        'email' => $user->email,
        'password' => $password,
    ]));
}

function createUserForCredentialLogin(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'password' => 'Password1!',
    ], $attributes));

    $user->roles()->attach(Role::factory()->create(['name' => 'editor']));

    return $user;
}

describe('User::canAuthenticate', function (): void {
    it('allows perpetual accounts with valid_from set and valid_to null', function (): void {
        $user = User::factory()->perpetual()->create();

        expect($user->canAuthenticate())->toBeTrue()
            ->and($user->isValid())->toBeTrue();
    });

    it('allows temporary accounts inside their window', function (): void {
        $user = User::factory()->temporary(now()->addDay())->create();

        expect($user->canAuthenticate())->toBeTrue();
    });

    it('denies expired accounts', function (): void {
        $user = User::factory()->expired()->create();

        expect($user->canAuthenticate())->toBeFalse()
            ->and($user->isExpired())->toBeTrue();
    });

    it('denies scheduled accounts', function (): void {
        $user = User::factory()->scheduled()->create();

        expect($user->canAuthenticate())->toBeFalse()
            ->and($user->isScheduled())->toBeTrue();
    });
});

describe('valid query scopes on User', function (): void {
    it('filters currently valid users', function (): void {
        $active = User::factory()->perpetual()->create();
        User::factory()->expired()->create();

        $ids = User::query()->valid()->pluck('id');

        expect($ids)->toContain($active->id)
            ->and($ids)->toHaveCount(1);
    });

    it('filters expired users', function (): void {
        User::factory()->perpetual()->create();
        $expired = User::factory()->expired()->create();

        $ids = User::query()->expired()->pluck('id');

        expect($ids)->toContain($expired->id)
            ->and($ids)->toHaveCount(1);
    });
});

describe('FortifyCredentialsProvider account validity', function (): void {
    it('authenticates a user inside the validity window', function (): void {
        $user = createUserForCredentialLogin([
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addWeek(),
        ]);

        $result = authenticateUserWithCredentials($user);

        expect($result['success'])->toBeTrue()
            ->and($result['user']?->is($user))->toBeTrue()
            ->and($result['error'])->toBeNull();
    });

    it('rejects expired users with account is not active', function (): void {
        $user = createUserForCredentialLogin([
            'valid_from' => now()->subWeek(),
            'valid_to' => now()->subDay(),
        ]);

        $result = authenticateUserWithCredentials($user);

        expect($result['success'])->toBeFalse()
            ->and($result['user'])->toBeNull()
            ->and($result['error'])->toBe('Account is not active');
    });

    it('rejects scheduled users with account is not active', function (): void {
        $user = createUserForCredentialLogin([
            'valid_from' => now()->addDay(),
            'valid_to' => now()->addWeek(),
        ]);

        $result = authenticateUserWithCredentials($user);

        expect($result['success'])->toBeFalse()
            ->and($result['error'])->toBe('Account is not active');
    });

    it('authenticates perpetual users with open-ended validity', function (): void {
        $user = createUserForCredentialLogin([
            'valid_from' => now()->subDay(),
            'valid_to' => null,
        ]);

        $result = authenticateUserWithCredentials($user);

        expect($result['success'])->toBeTrue()
            ->and($result['user']?->is($user))->toBeTrue();
    });

    it('authenticates by username when validity is configured', function (): void {
        $user = createUserForCredentialLogin([
            'valid_from' => now()->subDay(),
            'valid_to' => null,
        ]);

        $provider = app(FortifyCredentialsProvider::class);
        $result = $provider->authenticate(Request::create('/login', 'POST', [
            'username' => $user->username,
            'password' => 'Password1!',
        ]));

        expect($result['success'])->toBeTrue()
            ->and($result['user']?->is($user))->toBeTrue();
    });
});
