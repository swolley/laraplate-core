<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\HttpFoundation\Response;

uses(LaravelTestCase::class, RefreshDatabase::class);

it('has correct class structure', function (): void {
    $reflection = new ReflectionClass(LocalizationMiddleware::class);

    expect($reflection->getName())->toBe('Modules\Core\Http\Middleware\LocalizationMiddleware');
    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('handle'))->toBeTrue();
});

it('handle method has correct signature', function (): void {
    $reflection = new ReflectionMethod(LocalizationMiddleware::class, 'handle');

    expect($reflection->getNumberOfParameters())->toBe(2);
    expect($reflection->getReturnType()->getName())->toBe(Response::class);
});

it('sets locale from authenticated user lang', function (): void {
    $user = Mockery::mock();
    $user->lang = 'fr';

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $user);

    App::setLocale('en');

    $middleware = new LocalizationMiddleware();
    $middleware->handle($request, fn () => new Response());

    expect(App::getLocale())->toBe('fr');
});

it('does not change locale when user lang matches current locale', function (): void {
    $user = Mockery::mock();
    $user->lang = 'en';

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $user);

    App::setLocale('en');

    $middleware = new LocalizationMiddleware();
    $middleware->handle($request, fn () => new Response());

    expect(App::getLocale())->toBe('en');
});

it('sets locale from browser preferred language when no user', function (): void {
    $request = Request::create('/test', 'GET', [], [], [], [
        'HTTP_ACCEPT_LANGUAGE' => 'it_IT,it;q=0.9,en;q=0.8',
    ]);
    $request->setUserResolver(fn () => null);

    App::setLocale('en');

    $middleware = new LocalizationMiddleware();
    $middleware->handle($request, fn () => new Response());

    expect(App::getLocale())->toBe('it');
});

it('returns response from next handler', function (): void {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => null);

    $expected_response = new Response('test content');

    $middleware = new LocalizationMiddleware();
    $response = $middleware->handle($request, fn () => $expected_response);

    expect($response)->toBe($expected_response);
});
