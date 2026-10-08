<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Modules\Core\Auth\RedirectIfSecondFactorRequired;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Tests\Stubs\FakeAuthUser;
use Modules\Core\Tests\Stubs\FakeEnabledProvider;

afterEach(function (): void {
    Fortify::$authenticateUsingCallback = null;
});

function secondFactorRedirect(FakeEnabledProvider $provider): RedirectIfSecondFactorRequired
{
    return new RedirectIfSecondFactorRequired(
        Mockery::mock(StatefulGuard::class),
        Mockery::mock(LoginRateLimiter::class),
        new AuthenticationService([$provider]),
    );
}

it('passes the request on without validating credentials when the method carries a second factor', function (): void {
    $calls = 0;
    Fortify::authenticateUsing(function () use (&$calls): FakeAuthUser {
        $calls++;

        return new FakeAuthUser();
    });

    $provider = new FakeEnabledProvider('social');
    $provider->secondFactor = true;
    $request = Request::create('/login');
    $request->attributes->set('provider', 'social');

    $result = secondFactorRedirect($provider)->handle($request, static fn (): string => 'next');

    expect($result)->toBe('next')
        ->and($calls)->toBe(0);
});

it('defers to Fortify when the method does not carry a second factor', function (): void {
    $calls = 0;
    Fortify::authenticateUsing(function () use (&$calls): FakeAuthUser {
        $calls++;

        return new FakeAuthUser();
    });

    $provider = new FakeEnabledProvider('password');
    $request = Request::create('/login');
    $request->attributes->set('provider', 'password');

    $result = secondFactorRedirect($provider)->handle($request, static fn (): string => 'next');

    expect($result)->toBe('next')
        ->and($calls)->toBe(1);
});
