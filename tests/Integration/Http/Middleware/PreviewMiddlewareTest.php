<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Modules\Core\Http\Middleware\PreviewMiddleware;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    session()->put('preview', false);
    app()->instance('request', Request::create('/'));
});

/**
 * @param  Closure(Request): Response  $next
 */
$run = static function (Request $request, string $storage, Closure $next): Response {
    app()->instance('request', $request);

    return app(PreviewMiddleware::class)->handle($request, $next, $storage);
};

it('keeps preview off for the request when the param is absent', function () use ($run): void {
    // App and api never inherit a leftover session flag: the frontend must send the param.
    session()->put('preview', true);

    $run(
        Request::create('/app/crud/select/cms/contents', 'GET'),
        'request',
        function (Request $request): Response {
            expect(preview())->toBeFalse()
                ->and(session('preview'))->toBeTrue();

            return new Response;
        },
    );
});

it('enables preview for one request without writing the session', function () use ($run): void {
    $run(
        Request::create('/app/crud/select/cms/contents', 'GET', ['preview' => 'true']),
        'request',
        function (Request $request): Response {
            expect(preview())->toBeTrue()
                ->and(session('preview'))->toBeFalse();

            return new Response;
        },
    );

    // A subsequent request without the param must not see the previous flag.
    app()->instance('request', Request::create('/app/crud/select/cms/contents', 'GET'));

    expect(preview())->toBeFalse();
});

it('ignores a stale session flag in request mode', function () use ($run): void {
    session()->put('preview', true);

    $run(
        Request::create('/api/v1/crud/select/cms/contents', 'GET'),
        'request',
        function (Request $request): Response {
            expect(preview())->toBeFalse();

            return new Response;
        },
    );
});

it('persists the preview flag in the session on the admin surface', function () use ($run): void {
    $run(
        Request::create('/admin', 'GET', ['preview' => 'true']),
        'session',
        function (Request $request): Response {
            expect(preview())->toBeTrue()
                ->and(session('preview'))->toBeTrue();

            return new Response;
        },
    );

    // No param on the next admin request: the session still carries the toggle.
    $run(
        Request::create('/admin', 'GET'),
        'session',
        function (Request $request): Response {
            expect(preview())->toBeTrue();

            return new Response;
        },
    );
});

it('turns the session flag off when admin sends preview=false', function () use ($run): void {
    session()->put('preview', true);

    $run(
        Request::create('/admin', 'GET', ['preview' => 'false']),
        'session',
        function (Request $request): Response {
            expect(preview())->toBeFalse()
                ->and(session('preview'))->toBeFalse();

            return new Response;
        },
    );
});

it('registers request-scoped preview on the app and api surfaces', function (): void {
    $group = static fn (string $name): array => array_values(array_filter(
        app(Router::class)->getMiddlewareGroups()[$name] ?? [],
        'is_string',
    ));

    expect($group('web'))->toContain(PreviewMiddleware::class . ':request')
        ->and($group('api'))->toContain(PreviewMiddleware::class . ':request');
});
