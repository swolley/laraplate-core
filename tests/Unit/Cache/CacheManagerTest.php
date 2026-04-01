<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as IlluminateCacheRepository;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Cache\Repository;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('returns bound cache store for default driver', function (): void {
    config(['cache.default' => 'array']);
    $manager = new CacheManager($this->app);

    $first = $manager->store();
    $second = $manager->store();

    expect($first)->toBeInstanceOf(IlluminateCacheRepository::class)
        ->and($second)->toBe($first);
});

it('falls back to parent store for non-default driver', function (): void {
    config(['cache.default' => 'file']);
    $manager = new CacheManager($this->app);

    $store = $manager->store('array');

    expect($store)->toBeInstanceOf(Illuminate\Contracts\Cache\Repository::class);
});

it('creates core repository instances from store', function (): void {
    $manager = new CacheManager($this->app);

    $repository = $manager->repository(new ArrayStore(), ['driver' => 'array']);

    expect($repository)->toBeInstanceOf(Repository::class);
});
