<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Overrides\ModuleDocGenerator;
use Modules\Core\Overrides\ModuleDocRoute;
use Modules\Core\Tests\Stubs\DummyFormRequest;
use Modules\Core\Tests\TestCase;

uses(TestCase::class);

it('generateCombinations returns empty combination for empty parameters', function (): void {
    $config = ['ignoredRoutes' => []];
    $generator = new ModuleDocGenerator($config, 'Modules\\Core', null);

    $ref = new ReflectionMethod(ModuleDocGenerator::class, 'generateCombinations');

    $result = $ref->invoke($generator, []);

    expect($result)->toBe([[]]);
});

it('generateCombinations builds cartesian product of parameter values', function (): void {
    $config = ['ignoredRoutes' => []];
    $generator = new ModuleDocGenerator($config, 'Modules\\Core', null);

    $ref = new ReflectionMethod(ModuleDocGenerator::class, 'generateCombinations');

    $params = [
        'status' => ['draft', 'published'],
        'locale' => ['en', 'it'],
    ];

    $result = $ref->invoke($generator, $params);

    expect($result)->toHaveCount(4);
    expect($result)->toContain(
        ['status' => 'draft', 'locale' => 'en'],
        ['status' => 'draft', 'locale' => 'it'],
        ['status' => 'published', 'locale' => 'en'],
        ['status' => 'published', 'locale' => 'it'],
    );
});

it('iterateWheres collects parameter values from wheres array', function (): void {
    $config = ['ignoredRoutes' => []];
    $generator = new ModuleDocGenerator($config, 'Modules\\Core', null);

    $ref = new ReflectionMethod(ModuleDocGenerator::class, 'iterateWheres');

    $wheres = [
        'status' => 'draft|published',
        'locale' => ['en', 'it'],
    ];

    $parameter_values = [];
    $ref->invokeArgs($generator, [$wheres, &$parameter_values]);

    // iterateWheres accetta solo string|array, quindi con questi input
    // non ci sono esclusioni e otteniamo le liste attese.
    expect($parameter_values)->toHaveKey('status')
        ->and($parameter_values['status'])->toBe(['draft', 'published'])
        ->and($parameter_values)->toHaveKey('locale')
        ->and($parameter_values['locale'])->toBe(['en', 'it']);
});

it('shouldIgnoreRoute returns true for ignored route names and patterns', function (): void {
    $config = [
        'ignoredRoutes' => [
            'health.check',
            'debug*',
        ],
    ];
    $generator = new ModuleDocGenerator($config, 'Modules\\Core', null);

    $ref = new ReflectionMethod(ModuleDocGenerator::class, 'shouldIgnoreRoute');

    $route = new Route(['GET'], '/debug/info', ['as' => 'debug.info']);
    $route2 = new Route(['GET'], '/health/status', ['as' => 'health.check']);
    $route3 = new Route(['GET'], '/app/home', ['as' => 'app.home']);

    expect($ref->invoke($generator, $route))->toBeTrue()
        ->and($ref->invoke($generator, $route2))->toBeTrue()
        ->and($ref->invoke($generator, $route3))->toBeFalse();
});

it('iterateCombinations clones routes and replaces parameters', function (): void {
    $config = ['ignoredRoutes' => []];
    $generator = new ModuleDocGenerator($config, 'Modules\\Core', null);

    // Simula una route con parametri in wheres come atteso dal generator
    $route = new Route(['GET'], '/posts/{status}/{locale}', ['as' => 'posts.index']);
    $route->wheres = [
        'status' => ['draft', 'published'],
        'locale' => ['en', 'it'],
    ];

    $parameter_values = $route->wheres;

    $module_routes = [];

    $ref = new ReflectionMethod(ModuleDocGenerator::class, 'iterateCombinations');

    $ref->invokeArgs($generator, [$route, $parameter_values, &$module_routes]);

    expect($module_routes)->toHaveCount(4);
    expect($module_routes[0])->toBeInstanceOf(ModuleDocRoute::class);
});

it('getFormRules returns rules from non-abstract FormRequest parameter', function (): void {
    $config = ['ignoredRoutes' => []];
    $generator = new ModuleDocGenerator($config, 'Modules\\Core', null);

    $controller = new class()
    {
        public function store(DummyFormRequest $request): void {}
    };

    // Imposta la route in modo compatibile con Str::parseCallback(action())
    $route = new Route(['POST'], '/demo', ['uses' => $controller::class . '@store']);

    $route_prop = new ReflectionProperty(ModuleDocGenerator::class, 'route');
    $route_prop->setValue($generator, $route);

    $method_prop = new ReflectionProperty(ModuleDocGenerator::class, 'method');
    $method_prop->setValue($generator, 'post');

    // Aggiunge una macro "action" per rendere compatibile la chiamata in getActionClassInstance
    Route::macro('action', function () use ($route) {
        return $route->getAction('uses');
    });

    $ref = new ReflectionMethod(ModuleDocGenerator::class, 'getFormRules');

    $rules = $ref->invoke($generator);

    expect($rules)->toBe(['name' => 'required|string']);
});
