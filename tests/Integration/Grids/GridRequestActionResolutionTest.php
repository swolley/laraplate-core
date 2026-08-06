<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Grids\Casts\GridAction;
use Modules\Core\Grids\Requests\GridRequest;

it('resolves grid action from route name including module segment', function (): void {
    $request = Request::create('http://localhost/app/crud/grid/data/cms/contents', 'POST');
    $request->setRouteResolver(function () use ($request) {
        $route = new Illuminate\Routing\Route(['POST'], 'app/crud/grid/data/{module}/{entity}', []);
        $route->name('core.crud.grids.data');
        $route->bind($request);

        return $route;
    });

    $grid_request = GridRequest::createFrom($request);
    $grid_request->setContainer(app());
    $grid_request->setRedirector(app('redirect'));

    $method = new ReflectionMethod(GridRequest::class, 'resolveActionFromRequest');
    $method->setAccessible(true);

    expect($method->invoke($grid_request))->toBe(GridAction::Data);
});

it('resolves grid action from path when route name is unavailable', function (): void {
    $request = Request::create('http://localhost/app/crud/grid/select/cms/contents', 'GET');
    $grid_request = GridRequest::createFrom($request);
    $grid_request->setContainer(app());
    $grid_request->setRedirector(app('redirect'));

    $method = new ReflectionMethod(GridRequest::class, 'resolveActionFromRequest');
    $method->setAccessible(true);

    expect($method->invoke($grid_request))->toBe(GridAction::Select);
});

it('maps grids.replace route name to update action', function (): void {
    $request = Request::create('http://localhost/app/crud/grid/update/cms/contents', 'PATCH');
    $request->setRouteResolver(function () use ($request) {
        $route = new Illuminate\Routing\Route(['PATCH'], 'app/crud/grid/update/{module}/{entity}', []);
        $route->name('core.crud.grids.replace');
        $route->bind($request);

        return $route;
    });

    $grid_request = GridRequest::createFrom($request);
    $grid_request->setContainer(app());
    $grid_request->setRedirector(app('redirect'));

    $method = new ReflectionMethod(GridRequest::class, 'resolveActionFromRequest');
    $method->setAccessible(true);

    expect($method->invoke($grid_request))->toBe(GridAction::Update);
});
