<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Models\User;
use Modules\Core\Providers\FortifyServiceProvider;


beforeEach(function (): void {
    $this->provider = new FortifyServiceProvider(app());
    config([
        'fortify.home' => '/home',
        'fortify.redirects.login' => '/login-redirect',
        'fortify.redirects.logout' => '/logout-redirect',
        'fortify.redirects.register' => '/register-redirect',
    ]);
});

it('registers LogoutResponse instance', function (): void {
    $this->provider->register();

    expect(app()->bound(LogoutResponse::class))->toBeTrue();
    $response = app(LogoutResponse::class);
    expect($response)->toBeInstanceOf(LogoutResponse::class);
});

it('LogoutResponse redirects to userInfo when wantsJson', function (): void {
    $this->provider->register();

    $request = Request::create('/', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = app(LogoutResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toContain('profile-information');
});

it('registers LoginResponse instance', function (): void {
    $this->provider->register();

    expect(app()->bound(LoginResponse::class))->toBeTrue();
});

it('LoginResponse redirects to userInfo when wantsJson', function (): void {
    $this->provider->register();

    $request = Request::create('/', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = app(LoginResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toContain('profile-information');
});

it('LogoutResponse redirects to configured logout path when not json', function (): void {
    $this->provider->register();
    $request = Request::create('/', 'GET');

    $response = app(LogoutResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toContain('/logout-redirect');
});

it('LoginResponse redirects to configured login path when not json', function (): void {
    $this->provider->register();
    $request = Request::create('/', 'GET');

    $response = app(LoginResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toContain('/login-redirect');
});

it('registers RegisterResponse instance', function (): void {
    $this->provider->register();

    expect(app()->bound(RegisterResponse::class))->toBeTrue();
});

it('RegisterResponse redirects to userInfo when wantsJson', function (): void {
    $this->provider->register();
    $request = Request::create('/', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = app(RegisterResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toContain('profile-information');
});

it('RegisterResponse redirects to configured register path when not json', function (): void {
    $this->provider->register();
    $request = Request::create('/', 'GET');

    $response = app(RegisterResponse::class)->toResponse($request);

    expect($response->getTargetUrl())->toContain('/register-redirect');
});

it('registers AuthenticationService singleton', function (): void {
    $this->provider->register();

    expect(app()->bound(AuthenticationService::class))->toBeTrue();
    expect(app(AuthenticationService::class))->toBeInstanceOf(AuthenticationService::class);
});

it('boot runs without throwing', function (): void {
    $this->provider->register();
    $this->provider->boot();

    expect(true)->toBeTrue();
});

it('registers login rate limiter and builds throttle key from username and ip', function (): void {
    $this->provider->register();
    $this->provider->boot();
    config(['fortify.username' => 'email']);
    $request = Request::create('/', 'POST', ['email' => 'Tést.User@example.com']);
    $request->server->set('REMOTE_ADDR', '127.0.0.1');

    $limiter = RateLimiter::limiter('login');
    $limit = $limiter($request);
    $expected_key = Illuminate\Support\Str::transliterate(Illuminate\Support\Str::lower('Tést.User@example.com|127.0.0.1'));

    expect($limit)->toBeInstanceOf(Illuminate\Cache\RateLimiting\Limit::class)
        ->and($limit->maxAttempts)->toBe(5)
        ->and($limit->key)->toBe($expected_key);
});

it('authenticates via authentication service and stores license id when enabled', function (): void {
    $this->provider->register();
    config(['auth.enable_user_licenses' => true]);
    $user = User::factory()->create();
    $license = (object) ['id' => 99];
    $service = new AuthenticationService([
        new class($user, $license) implements IAuthenticationProvider
        {
            public function __construct(private User $user, private object $license) {}

            public function canHandle(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): array
            {
                return [
                    'success' => true,
                    'user' => $this->user,
                    'error' => null,
                    'license' => $this->license,
                ];
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getProviderName(): string
            {
                return 'test';
            }
        },
    ]);
    app()->instance(AuthenticationService::class, $service);
    $this->provider->boot();

    $session = app('session')->driver();
    $session->start();
    $request = Request::create('/', 'POST');
    $request->setLaravelSession($session);
    $callback = Fortify::$authenticateUsingCallback;
    $result = $callback($request);

    expect($result)->toBe($user)
        ->and(session('license_id'))->toBe(99);
});

it('returns null when authentication service reports failure', function (): void {
    $this->provider->register();
    $service = new AuthenticationService([
        new class() implements IAuthenticationProvider
        {
            public function canHandle(Request $request): bool
            {
                return true;
            }

            public function authenticate(Request $request): array
            {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => 'denied',
                    'license' => null,
                ];
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getProviderName(): string
            {
                return 'test';
            }
        },
    ]);
    app()->instance(AuthenticationService::class, $service);
    $this->provider->boot();

    $session = app('session')->driver();
    $session->start();
    $request = Request::create('/', 'POST');
    $request->setLaravelSession($session);
    $callback = Fortify::$authenticateUsingCallback;
    $result = $callback($request);

    expect($result)->toBeNull();
});
