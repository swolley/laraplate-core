<?php

declare(strict_types=1);

use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Http\Requests\ModifyRequest;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('__get returns value from changes when key exists', function (): void {
    $request = new ModifyRequest(request: ['foo' => 'bar']);

    $data = new ModifyRequestData($request, 'settings', ['settings.title' => 'test_value'], 'id');

    expect($data->title)->toBe('test_value');
});

it('__get falls back to request input when key not in changes', function (): void {
    $base = Symfony\Component\HttpFoundation\Request::create('/test', 'POST', ['missing_key' => 'from_input']);
    $request = ModifyRequest::createFromBase($base);

    $data = new ModifyRequestData($request, 'settings', ['settings.name' => 'test'], 'id');

    expect($data->missing_key)->toBe('from_input');
});

it('__get falls back to route parameter when input is null', function (): void {
    $base = Symfony\Component\HttpFoundation\Request::create('/api/settings/update/42', 'PUT');
    $request = ModifyRequest::createFromBase($base);
    $route = new Illuminate\Routing\Route('PUT', '/api/{entity}/update/{pk_val}', []);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $data = new ModifyRequestData($request, 'settings', ['settings.name' => 'test'], 'pk_val');

    expect($data->pk_val)->toBe('42');
});
