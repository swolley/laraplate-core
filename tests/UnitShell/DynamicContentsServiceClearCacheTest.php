<?php

declare(strict_types=1);

/**
 * Uses the application {@see Tests\TestCase} (full Laravel bootstrap) so {@see Cache} facade
 * mocking works. {@see Modules\Core\Tests\LaravelTestCase} is avoided here because it runs
 * module migrations that are incompatible with the default SQLite in-memory test database.
 *
 * This file lives under {@see UnitShell} so Pest does not apply {@see Modules\Core\Tests\LaravelTestCase}
 * to the whole {@see Unit} directory binding.
 */
use Illuminate\Contracts\Cache\Repository as CacheRepositoryContract;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Services\DynamicContentsService;

uses(Tests\TestCase::class);

afterEach(function (): void {
    Mockery::close();
    DynamicContentsService::reset();
});

it('clearPresettablesCache forgets the memo store entry for the presettables table', function (): void {
    $memo_repository = Mockery::mock(CacheRepositoryContract::class);
    $memo_repository->shouldReceive('forget')->once()->with('presettables');

    Cache::shouldReceive('memo')->once()->andReturn($memo_repository);

    DynamicContentsService::getInstance()->clearPresettablesCache();
});

it('clearAllCaches forgets memo store entries for entities, presets, and presettables', function (): void {
    $memo_repository = Mockery::mock(CacheRepositoryContract::class);
    $memo_repository->shouldReceive('forget')->once()->with('entities');
    $memo_repository->shouldReceive('forget')->once()->with('presets');
    $memo_repository->shouldReceive('forget')->once()->with('presettables');

    Cache::shouldReceive('memo')->times(3)->andReturn($memo_repository);

    DynamicContentsService::getInstance()->clearAllCaches();
});

it('rememberForeverCollection reloads when the persistent cache returns a plain array', function (): void {
    config()->set('cache.default', 'array');
    Cache::flush();
    app()->forgetInstance('cache.__memoized:array');
    DynamicContentsService::reset();

    $cache_key = 'core.dynamic_contents.test:array-rehydrate';
    Cache::forever($cache_key, ['not' => 'a-collection']);

    $service = DynamicContentsService::getInstance();
    $method = new ReflectionMethod(DynamicContentsService::class, 'rememberForeverCollection');
    $method->setAccessible(true);

    $fresh = new Illuminate\Database\Eloquent\Collection();
    $result = $method->invoke($service, $cache_key, static fn (): Illuminate\Database\Eloquent\Collection => $fresh);

    expect($result)->toBe($fresh)
        ->and($result)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
});
