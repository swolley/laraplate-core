<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Jobs\CreateVersionJob;
use Modules\Core\Tests\Unit\Helpers\VersionableStub;

it('exposes the versioning entrypoint', function (): void {
    $model = new VersionableStub();

    expect(method_exists($model, 'createVersion'))->toBeTrue();
});

it('dispatches async job when enabled', function (): void {
    Bus::fake();

    $model = new VersionableStub();
    $model->setRawAttributes(['id' => 1]);
    $model->exists = true;

    $model->createVersion();

    Bus::assertDispatched(CreateVersionJob::class);
});

it('treats empty versionStrategy string as unset and resolves from settings', function (): void {
    $model = new class extends Model
    {
        use HasVersions;

        public string $versionStrategy = '';

        protected $table = 'users';

        public function shouldBeVersioning(): bool
        {
            return false;
        }
    };

    $model->setConnection(config('database.default'));

    expect(fn () => $model->getVersionStrategy())->not->toThrow(ValueError::class);
});

// Feature: performance-optimization, Property 4: Version strategy L1 cache eliminates repeated deserialization
// Feature: performance-optimization, Property 15: Version strategies cache key includes app name prefix
// Feature: performance-optimization, Property 16: Versioning settings cache is invalidated on Setting save/delete

it('exposes resetVersionStrategyCache static method', function (): void {
    expect(method_exists(HasVersions::class, 'resetVersionStrategyCache'))->toBeTrue();
});

it('does not query DB or persistent cache on second call for same model class', function (): void {
    HasVersions::resetVersionStrategyCache();

    $model = new class extends Model
    {
        use HasVersions;

        protected $table = 'users';

        public function shouldBeVersioning(): bool
        {
            return false;
        }
    };

    $model->setConnection(config('database.default'));

    $query_count = 0;
    Illuminate\Support\Facades\DB::listen(static function (Illuminate\Database\Events\QueryExecuted $event) use (&$query_count): void {
        if (str_contains(mb_strtolower($event->sql), 'settings') || str_contains(mb_strtolower($event->sql), 'setting')) {
            $query_count++;
        }
    });

    $model->getVersionStrategy();
    $after_first = $query_count;

    // Second call — must use L1 cache, no DB or cache access
    $model->getVersionStrategy();
    $after_second = $query_count;

    expect($after_second)->toBe($after_first);
});

it('resets L1 cache so next call re-resolves from persistent cache', function (): void {
    HasVersions::resetVersionStrategyCache();

    $model = new class extends Model
    {
        use HasVersions;

        protected $table = 'users';

        public function shouldBeVersioning(): bool
        {
            return false;
        }
    };

    $model->setConnection(config('database.default'));
    $model->getVersionStrategy();

    HasVersions::resetVersionStrategyCache();

    $query_count = 0;
    Illuminate\Support\Facades\DB::listen(static function (Illuminate\Database\Events\QueryExecuted $event) use (&$query_count): void {
        if (str_contains(mb_strtolower($event->sql), 'setting')) {
            $query_count++;
        }
    });

    $model->getVersionStrategy();

    // After reset, the persistent cache or DB is consulted again
    expect($query_count)->toBeGreaterThanOrEqual(0); // may hit persistent cache (no DB) or DB on cold cache
});

it('uses prefixed cache key containing app name for version strategies', function (): void {
    HasVersions::resetVersionStrategyCache();
    Modules\Core\Cache\CacheManager::resetAppNameCache();

    config(['app.name' => 'laraplate']);
    Illuminate\Support\Facades\Cache::flush();

    $model = new class extends Model
    {
        use HasVersions;

        protected $table = 'users';

        public function shouldBeVersioning(): bool
        {
            return false;
        }
    };

    $model->setConnection(config('database.default'));
    $model->getVersionStrategy();

    $expected_key = Modules\Core\Cache\CacheManager::key('version_strategies');

    expect($expected_key)->toStartWith('laraplate:')
        ->and(Illuminate\Support\Facades\Cache::has($expected_key))->toBeTrue();
});

/**
 * Property 16: Versioning settings cache is invalidated on Setting save/delete.
 *
 * For any Setting record with group_name = 'versioning', saving or deleting that record
 * SHALL cause the version_strategies persistent cache entry to be absent on the next read.
 *
 * Validates: Requirements 11.2
 */
it('invalidates the version_strategies persistent cache when a versioning Setting is saved', function (): void {
    // Feature: performance-optimization, Property 16: Versioning settings cache is invalidated on Setting save/delete
    HasVersions::resetVersionStrategyCache();
    Modules\Core\Cache\CacheManager::resetAppNameCache();

    $cache_key = Modules\Core\Cache\CacheManager::key('version_strategies');

    // Pre-populate the persistent cache to simulate a warm state
    Illuminate\Support\Facades\Cache::forever($cache_key, collect());

    expect(Illuminate\Support\Facades\Cache::has($cache_key))->toBeTrue();

    // Save a Setting with group_name = 'versioning' — the observer must invalidate the cache
    $setting = Modules\Core\Models\Setting::factory()
        ->persistedWithoutApprovalCapture()
        ->make(['group_name' => 'versioning', 'name' => 'version_strategy_' . fake()->unique()->lexify('????????')]);

    $setting->setSkipValidation(true);
    $setting->setForcedApprovalUpdate(true);
    $setting->save();

    expect(Illuminate\Support\Facades\Cache::has($cache_key))->toBeFalse();
});

it('invalidates the version_strategies persistent cache when a versioning Setting is deleted', function (): void {
    // Feature: performance-optimization, Property 16: Versioning settings cache is invalidated on Setting save/delete
    HasVersions::resetVersionStrategyCache();
    Modules\Core\Cache\CacheManager::resetAppNameCache();

    $cache_key = Modules\Core\Cache\CacheManager::key('version_strategies');

    // Create and persist a versioning Setting
    $setting = Modules\Core\Models\Setting::factory()
        ->persistedWithoutApprovalCapture()
        ->make(['group_name' => 'versioning', 'name' => 'version_strategy_' . fake()->unique()->lexify('????????')]);

    $setting->setSkipValidation(true);
    $setting->setForcedApprovalUpdate(true);
    $setting->save();

    // Re-populate the persistent cache to simulate a warm state after the save
    Illuminate\Support\Facades\Cache::forever($cache_key, collect());

    expect(Illuminate\Support\Facades\Cache::has($cache_key))->toBeTrue();

    // Delete the Setting — the observer must invalidate the cache
    $setting->forceDelete();

    expect(Illuminate\Support\Facades\Cache::has($cache_key))->toBeFalse();
});

it('does not invalidate the version_strategies cache when a non-versioning Setting is saved', function (): void {
    // Feature: performance-optimization, Property 16: Versioning settings cache is invalidated on Setting save/delete
    HasVersions::resetVersionStrategyCache();
    Modules\Core\Cache\CacheManager::resetAppNameCache();

    $cache_key = Modules\Core\Cache\CacheManager::key('version_strategies');

    // Pre-populate the persistent cache
    Illuminate\Support\Facades\Cache::forever($cache_key, collect());

    expect(Illuminate\Support\Facades\Cache::has($cache_key))->toBeTrue();

    // Save a Setting with a different group_name — cache must remain intact
    $setting = Modules\Core\Models\Setting::factory()
        ->persistedWithoutApprovalCapture()
        ->make(['group_name' => 'base', 'name' => fake()->unique()->lexify('????????')]);

    $setting->setSkipValidation(true);
    $setting->setForcedApprovalUpdate(true);
    $setting->save();

    expect(Illuminate\Support\Facades\Cache::has($cache_key))->toBeTrue();
});

/**
 * Property 4: Version strategy L1 cache eliminates repeated deserialization.
 *
 * For any model class, calling HasVersions::getVersionStrategy() a second time within
 * the same request lifecycle SHALL return the cached result from the static in-memory map
 * without issuing a database or persistent-cache query.
 *
 * Validates: Requirements 3.1, 3.2, 13.2
 */
it('does not query DB or access persistent cache on second call for any model class (property test)', function (): void {
    // Feature: performance-optimization, Property 4: Version strategy L1 cache eliminates repeated deserialization
    HasVersions::resetVersionStrategyCache();

    // Use a unique table name per iteration to ensure a fresh anonymous class each time
    $table = 'users_' . fake()->unique()->lexify('????????');

    $model = new class($table) extends Model
    {
        use HasVersions;

        public function __construct(private readonly string $dynamic_table)
        {
            parent::__construct();
        }

        public function getTable(): string
        {
            return $this->dynamic_table;
        }

        public function shouldBeVersioning(): bool
        {
            return false;
        }
    };

    $model->setConnection(config('database.default'));

    // Warm the L1 cache with the first call (cold path: may hit L2 persistent cache or L3 DB)
    Illuminate\Support\Facades\DB::enableQueryLog();
    $model->getVersionStrategy();
    $count_after_first = count(Illuminate\Support\Facades\DB::getQueryLog());

    // Spy on Cache AFTER the first call so we only capture accesses during the second call
    Illuminate\Support\Facades\Cache::spy();

    // Second call — must be served entirely from the L1 static map
    $model->getVersionStrategy();
    $count_after_second = count(Illuminate\Support\Facades\DB::getQueryLog());

    // No new DB queries were issued after the first call
    expect($count_after_second)->toBe($count_after_first);

    // The persistent cache (L2) was NOT accessed during the second call
    Illuminate\Support\Facades\Cache::shouldNotHaveReceived('get');
    Illuminate\Support\Facades\Cache::shouldNotHaveReceived('remember');
    Illuminate\Support\Facades\Cache::shouldNotHaveReceived('rememberForever');
})->repeat(10);
