<?php

declare(strict_types=1);

use Modules\Core\Overrides\ModuleDocGenerator;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('constructor accepts config module and route filter', function (): void {
    $config = ['ignoredRoutes' => []];
    $generator = new ModuleDocGenerator($config, 'Modules\Core', null);

    $ref = new \ReflectionClass($generator);
    $prop = $ref->getProperty('module');
    expect($prop->getValue($generator))->toBe('Modules\Core');
});

it('getAppRoutes returns array', function (): void {
    $config = [];
    $generator = new ModuleDocGenerator($config, 'Modules\Core', null);

    $method = (new \ReflectionClass($generator))->getMethod('getAppRoutes');
    $routes = $method->invoke($generator);

    expect($routes)->toBeArray();
});
