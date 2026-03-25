<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Core\Providers\RouteServiceProvider as CoreRouteServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('getPrefix returns slug of module name for Core', function (): void {
    $provider = new CoreRouteServiceProvider(app());

    $ref = new ReflectionClass($provider);
    $prop = $ref->getProperty('name');
    expect($prop->getValue($provider))->toBe('Core');

    $method = $ref->getMethod('getPrefix');
    $prefix = $method->invoke($provider);

    expect($prefix)->toBe(Str::slug('Core'));
});

it('getModuleNamespace returns namespace derived from Providers replacement', function (): void {
    $provider = new CoreRouteServiceProvider(app());

    $method = (new ReflectionClass($provider))->getMethod('getModuleNamespace');
    $namespace = $method->invoke($provider);

    expect($namespace)->toBeString();
    expect($namespace)->not->toContain('Providers');
});
