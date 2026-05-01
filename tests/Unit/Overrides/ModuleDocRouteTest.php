<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Overrides\ModuleDocRoute;


it('constructs from Laravel route', function (): void {
    $route = new Route(['GET'], '/test', fn () => null);
    $route->name('core.api.foo');

    $doc_route = new ModuleDocRoute($route);

    expect($doc_route)->toBeInstanceOf(ModuleDocRoute::class);
});

it('name returns route action as', function (): void {
    $route = new Route(['GET'], '/test', fn () => null);
    $route->name('core.api.users.index');

    $doc_route = new ModuleDocRoute($route);

    expect($doc_route->name())->toBe('core.api.users.index');
});

it('name returns null when route has no name', function (): void {
    $route = new Route(['GET'], '/test', fn () => null);

    $doc_route = new ModuleDocRoute($route);

    expect($doc_route->name())->toBeNull();
});

it('group returns first segment of route name', function (): void {
    $route = new Route(['GET'], '/test', fn () => null);
    $route->name('core.api.foo.bar');

    $doc_route = new ModuleDocRoute($route);

    expect($doc_route->group())->toBe('core');
});

it('group returns empty string when name is null', function (): void {
    $route = new Route(['GET'], '/test', fn () => null);

    $doc_route = new ModuleDocRoute($route);

    expect($doc_route->group())->toBe('');
});
