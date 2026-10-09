<?php

declare(strict_types=1);

use Filament\Panel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\ApiTokenService;
use Modules\Core\Support\CrudApiExposure;

const SERVICE_ACCOUNT_TEST_PERMISSION = 'default.users.select';

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('api');
    config()->set('permission.roles.superadmin', 'superadmin');
    config()->set('core.api.rate_limit_per_minute', 600);
});

/**
 * A service account of the class the auth provider hands out, holding the select permission of the users
 * entity through an api role, and a real password so that only the account type can refuse the sign-in.
 */
function serviceAccountTestAccount(bool $serviceAccount = true): App\Models\User
{
    foreach (['web', 'api'] as $guard) {
        Permission::query()->firstOrCreate(['name' => SERVICE_ACCOUNT_TEST_PERMISSION, 'guard_name' => $guard]);
    }

    $role = Role::factory()->create(['name' => 'sa_role_' . uniqid(), 'guard_name' => 'api']);
    $role->givePermissionTo(Permission::query()->where(['name' => SERVICE_ACCOUNT_TEST_PERMISSION, 'guard_name' => 'api'])->firstOrFail());

    $factory = User::factory()->state(['password' => 'Password1!', 'email_verified_at' => now()]);
    $raw = ($serviceAccount ? $factory->serviceAccount() : $factory)->create();

    $account = App\Models\User::query()->findOrFail($raw->getKey());
    $account->assignRole($role);

    return $account->fresh();
}

describe('a service account cannot sign in', function (): void {
    it('is refused by the Fortify login endpoint while an ordinary account with the same grants is let in', function (): void {
        $ordinary = serviceAccountTestAccount(serviceAccount: false);

        $this->postJson(route('login.store'), ['email' => $ordinary->email, 'password' => 'Password1!'])->assertSuccessful();
        $this->assertAuthenticatedAs($ordinary);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $service = serviceAccountTestAccount();

        $this->postJson(route('login.store'), ['email' => $service->email, 'password' => 'Password1!'])->assertStatus(422);
        $this->assertGuest();
    });

    it('is refused by the authentication service whatever the provider', function (): void {
        $service = serviceAccountTestAccount();

        $result = app(Modules\Core\Auth\Services\AuthenticationService::class)->authenticate(
            Illuminate\Http\Request::create('/login', 'POST', ['email' => $service->email, 'password' => 'Password1!']),
        );

        expect($result['success'])->toBeFalse()
            ->and($result['user'])->toBeNull();
    });

    it('is refused as a passkey login', function (): void {
        $service = serviceAccountTestAccount();

        expect(app(Modules\Core\Auth\PasskeyLoginAuthorizer::class)->error($service))->not->toBeNull();
    });

    it('cannot open a Filament panel, superadmin or not', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('getAuthGuard')->andReturn('web');

        $service = serviceAccountTestAccount();
        expect($service->canAccessPanel($panel))->toBeFalse();

        $service->assignRole(Role::findOrCreate('superadmin', 'web'));
        expect($service->fresh()->canAccessPanel($panel))->toBeFalse();
    });
});

describe('a service account is never anonymous', function (): void {
    it('is not a guest even with no email, while an ordinary account without email still is', function (): void {
        $service = User::factory()->serviceAccount()->make(['email' => null]);
        $ordinary = User::factory()->make(['email' => null]);

        expect($service->isGuest())->toBeFalse()
            ->and($ordinary->isGuest())->toBeTrue();
    });
});

describe('the guest account stays the guest', function (): void {
    it('is a guest by its configured name or username even when flagged as a service account', function (): void {
        config()->set('permission.users.guest', 'visitor');

        $by_name = User::factory()->serviceAccount()->make(['name' => 'visitor', 'username' => 'x1']);
        $by_username = User::factory()->serviceAccount()->make(['name' => 'Someone', 'username' => 'visitor']);

        expect($by_name->isGuest())->toBeTrue()
            ->and($by_username->isGuest())->toBeTrue();
    });

    it('resolves the anonymous user deterministically when a service account shares its name', function (): void {
        Cache::flush();
        config()->set('permission.users.guest', 'visitor');
        $impostor = App\Models\User::query()->findOrFail(User::factory()->serviceAccount()->create(['name' => 'visitor', 'username' => 'svc-visitor'])->getKey());
        $real = App\Models\User::query()->findOrFail(User::factory()->create(['name' => 'visitor', 'username' => 'visitor'])->getKey());

        $resolved = app(Modules\Core\Services\Authorization\AuthorizationService::class)->resolveAnonymousUser(Illuminate\Http\Request::create('/'));

        expect($resolved?->getKey())->toBe($real->getKey())->not->toBe($impostor->getKey());
    });

    it('prefers the account that is not a service account when only the name collides', function (): void {
        Cache::flush();
        config()->set('permission.users.guest', 'visitor');
        App\Models\User::query()->findOrFail(User::factory()->serviceAccount()->create(['name' => 'visitor', 'username' => 'svc-visitor'])->getKey());
        $real = App\Models\User::query()->findOrFail(User::factory()->create(['name' => 'visitor', 'username' => 'the-visitor'])->getKey());

        $resolved = app(Modules\Core\Services\Authorization\AuthorizationService::class)->resolveAnonymousUser(Illuminate\Http\Request::create('/'));

        expect($resolved?->getKey())->toBe($real->getKey());
    });

    it('never issues a token for the guest account', function (): void {
        config()->set('permission.users.guest', 'visitor');
        $guest = serviceAccountTestAccount(serviceAccount: false);
        $guest->forceFill(['name' => 'visitor'])->save();

        expect(fn () => app(ApiTokenService::class)->issue($guest->fresh(), 'x', [SERVICE_ACCOUNT_TEST_PERMISSION], now()->addDay(), null))
            ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        expect(Laravel\Sanctum\PersonalAccessToken::query()->count())->toBe(0);
    });
});

describe('a service account is not impersonable', function (): void {
    it('cannot be impersonated while an ordinary account can', function (): void {
        expect(serviceAccountTestAccount()->canBeImpersonated())->toBeFalse()
            ->and(serviceAccountTestAccount(serviceAccount: false)->canBeImpersonated())->toBeTrue();
    });
});

describe('a service account authenticates with tokens only', function (): void {
    it('is served on the api with a non-expiring token issued for it', function (): void {
        CrudApiExposure::enable();
        $service = serviceAccountTestAccount();

        $token = app(ApiTokenService::class)->issue($service, 'machine', [SERVICE_ACCOUNT_TEST_PERMISSION], null, null);

        expect($token->accessToken->expires_at)->toBeNull();

        $this->getJson('/api/v1/select/core/users', ['Authorization' => 'Bearer ' . $token->plainTextToken])->assertOk();
    });

    it('keeps the limits of the abilities and the network on a service account token', function (): void {
        $service = serviceAccountTestAccount();

        expect(fn () => app(ApiTokenService::class)->issue($service, 'machine', ['default.roles.delete'], null, null))
            ->toThrow(Illuminate\Validation\ValidationException::class)
            ->and(fn () => app(ApiTokenService::class)->issue($service, 'machine', ['*'], null, null))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });

    it('still expires a service account token that was given an expiry', function (): void {
        $service = serviceAccountTestAccount();

        $token = app(ApiTokenService::class)->issue($service, 'machine', [SERVICE_ACCOUNT_TEST_PERMISSION], now()->addDays(800), ['10.0.0.0/8']);

        expect($token->accessToken->expires_at)->not->toBeNull()
            ->and(json_decode((string) $token->accessToken->getRawOriginal('allowed_cidrs'), true))->toBe(['10.0.0.0/8']);
    });

    it('leaves no unrestricted token behind when the network restriction cannot be saved', function (): void {
        $service = serviceAccountTestAccount();
        Laravel\Sanctum\PersonalAccessToken::updating(static function (): void {
            throw new RuntimeException('second write failed');
        });

        try {
            expect(fn () => app(ApiTokenService::class)->issue($service, 'machine', [SERVICE_ACCOUNT_TEST_PERMISSION], null, ['10.0.0.0/8']))
                ->toThrow(RuntimeException::class);
        } finally {
            Laravel\Sanctum\PersonalAccessToken::flushEventListeners();
        }

        expect(Laravel\Sanctum\PersonalAccessToken::query()->count())->toBe(0);
    });

    it('does not let a non-service account have a non-expiring token through the service', function (): void {
        $ordinary = serviceAccountTestAccount(serviceAccount: false);

        expect(fn () => app(ApiTokenService::class)->issue($ordinary, 'personal', [SERVICE_ACCOUNT_TEST_PERMISSION], null, null))
            ->toThrow(Illuminate\Validation\ValidationException::class);
    });
});
