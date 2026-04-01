<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Modules\Core\Http\Middleware\AddContext;

it('adds context and calls next', function (): void {
    Context::shouldReceive('add')->once()->with(Mockery::on(function (array $data): bool {
        return isset($data['scope'], $data['locale'], $data['url'])
            && $data['scope'] === 'web';
    }));

    $request = Request::create('https://example.com/foo');
    $next = fn ($req) => response('ok');
    $middleware = new AddContext;

    $response = $middleware->handle($request, $next);

    expect($response->getContent())->toBe('ok');
});
