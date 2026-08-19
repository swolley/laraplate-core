<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\CrudAuthoringContextMiddleware;

test('middleware flags the request as the authoring surface', function (): void {
    $request = Request::create('/app/crud/select/cms/contents');
    app()->instance('request', $request);

    $seen = null;
    $next = function (Request $req) use (&$seen) {
        $seen = authoring_surface();

        return response('ok');
    };

    $response = (new CrudAuthoringContextMiddleware)->handle($request, $next);

    expect($response->getContent())->toBe('ok')
        ->and($seen)->toBeTrue();
});

test('authoring surface defaults to off without the middleware', function (): void {
    app()->instance('request', Request::create('/api/v1/select/cms/contents'));

    expect(authoring_surface())->toBeFalse();
});

test('authoring surface is off outside a request context', function (): void {
    app()->forgetInstance('request');

    expect(authoring_surface())->toBeFalse();
});
