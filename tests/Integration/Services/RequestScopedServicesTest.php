<?php

declare(strict_types=1);

use Modules\Core\Services\DynamicContentsService;
use Modules\Core\Services\DynamicEntityService;

/*
|--------------------------------------------------------------------------
| Both services memoize hydrated models for the current request only. They are
| bound with scoped(), so the container drops them at every request boundary.
| A long-lived worker (Octane) calls forgetScopedInstances() between requests:
| these tests assert the instances do not survive it, which a process-level
| singleton would have done.
|--------------------------------------------------------------------------
*/

dataset('request scoped services', [
    'dynamic contents' => [DynamicContentsService::class],
    'dynamic entities' => [DynamicEntityService::class],
]);

it('resolves the same instance for the whole request', function (string $service): void {
    expect($service::getInstance())->toBe($service::getInstance())
        ->and(app($service))->toBe($service::getInstance());
})->with('request scoped services');

it('drops the instance at a request boundary', function (string $service): void {
    $before = $service::getInstance();

    app()->forgetScopedInstances();

    expect($service::getInstance())->not->toBe($before);
})->with('request scoped services');

it('is registered as a scoped binding, not a singleton', function (string $service): void {
    expect(app()->isShared($service))->toBeTrue();

    $reflection = new ReflectionProperty(Illuminate\Container\Container::class, 'scopedInstances');

    expect($reflection->getValue(app()))->toContain($service);
})->with('request scoped services');

it('does not carry memoized state into the next request', function (): void {
    $service = DynamicEntityService::getInstance();
    $cache = new ReflectionProperty(DynamicEntityService::class, 'resolved_cache');

    $cache->setValue($service, ['dynamic_entities.default.leaked' => 'from previous request']);

    app()->forgetScopedInstances();

    expect($cache->getValue(DynamicEntityService::getInstance()))->toBe([]);
});
