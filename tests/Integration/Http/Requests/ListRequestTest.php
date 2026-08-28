<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Http\Requests\ListRequest;


it('prepareForValidation parses sort filters and group_by json strings', function (): void {
    $request = ListRequest::create('/core/api/list/settings', 'GET', [
        'sort' => '[{"property":"name","direction":"asc"}]',
        'filters' => '[{"property":"name","value":"john"}]',
        'group_by' => '["name"]',
    ]);

    $route = new Route('GET', '/core/api/list/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);

    $method = new ReflectionMethod(ListRequest::class, 'prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('sort'))->toBeArray()
        ->and($request->input('filters'))->toBeArray()
        ->and($request->input('group_by'))->toBeArray();
});

it('prepareForValidation keeps non-json sort filters and group_by values untouched', function (): void {
    $request = ListRequest::create('/core/api/list/settings', 'GET', [
        'sort' => ['name:asc'],
        'filters' => ['a' => 1],
        'group_by' => ['name'],
    ]);

    $route = new Route('GET', '/core/api/list/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);

    $method = new ReflectionMethod(ListRequest::class, 'prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('sort'))->toBe(['name:asc'])
        ->and($request->input('filters'))->toBe(['a' => 1])
        ->and($request->input('group_by'))->toBe(['name']);
});

it('coerces count and totals boolean flags before validation', function (): void {
    $request = ListRequest::create('/app/crud/select/core/settings', 'GET', [
        'count' => 'true',
        'totals' => 'no',
    ]);

    $route = new Route('GET', '/app/crud/select/{module}/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('module', 'core');
    $route->setParameter('entity', 'settings');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setRouteResolver(fn (): Route => $route);

    $method = new ReflectionMethod(ListRequest::class, 'prepareForValidation');
    $method->setAccessible(true);
    $method->invoke($request);

    expect($request->input('count'))->toBeTrue()
        ->and($request->input('totals'))->toBeFalse();
});
