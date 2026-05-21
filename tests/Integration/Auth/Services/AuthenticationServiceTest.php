<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Tests\Stubs\FakeAuthUser;
use Modules\Core\Tests\Stubs\FakeDisabledProvider;
use Modules\Core\Tests\Stubs\FakeEnabledProvider;

it('returns first successful authentication result from enabled providers', function (): void {
    $request = new Request();
    $request->attributes->set('provider', 'first');

    $disabled = new FakeDisabledProvider('disabled');
    $first = new FakeEnabledProvider('first');
    $second = new FakeEnabledProvider('second');

    $service = new AuthenticationService([$disabled, $first, $second]);

    $result = $service->authenticate($request);

    expect($result['success'])->toBeTrue()
        ->and($result['user'])->toBeInstanceOf(FakeAuthUser::class)
        ->and($result['error'])->toBeNull()
        ->and($result['license'])->toBeNull()
        ->and($first->handleCalled)->toBeTrue()
        ->and($second->handleCalled)->toBeFalse();
});

it('falls back to error structure when no provider can handle the request', function (): void {
    $request = new Request();
    $request->attributes->set('provider', 'unknown');

    $p1 = new FakeEnabledProvider('first');
    $p2 = new FakeDisabledProvider('second');

    $service = new AuthenticationService([$p1, $p2]);

    $result = $service->authenticate($request);

    expect($result)->toMatchArray([
        'success' => false,
        'user' => null,
        'error' => 'No suitable authentication provider found',
        'license' => null,
    ]);
});

it('getAvailableProviders returns names of enabled providers only', function (): void {
    $p1 = new FakeEnabledProvider('first');
    $p2 = new FakeDisabledProvider('second');
    $p3 = new FakeEnabledProvider('third');

    $service = new AuthenticationService([$p1, $p2, $p3]);

    $providers = $service->getAvailableProviders();

    expect(array_values($providers))->toBe(['first', 'third']);
});
