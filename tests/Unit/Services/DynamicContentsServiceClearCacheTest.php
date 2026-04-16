<?php

declare(strict_types=1);

/**
 * Uses the application {@see Tests\TestCase} (full Laravel bootstrap) so {@see Cache} facade
 * mocking works. {@see Modules\Core\Tests\LaravelTestCase} is avoided here because it runs
 * module migrations that are incompatible with the default SQLite in-memory test database.
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
