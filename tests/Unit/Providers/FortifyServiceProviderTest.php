<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Providers\FortifyServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->provider = new FortifyServiceProvider(app());
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

it('registers RegisterResponse instance', function (): void {
    $this->provider->register();

    expect(app()->bound(RegisterResponse::class))->toBeTrue();
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
