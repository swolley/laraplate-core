<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Cache\HasCache;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('getCacheKey returns cacheKey property when it exists', function (): void {
    $model = new class extends Model
    {
        use HasCache;

        public string $cacheKey = 'custom_cache_key';

        protected $table = 'test_table';
    };

    expect($model->getCacheKey())->toBe('custom_cache_key');
});

it('getCacheKey returns table name when cacheKey property does not exist', function (): void {
    $model = new class extends Model
    {
        use HasCache;

        protected $table = 'test_table';
    };

    expect($model->getCacheKey())->toBe('test_table');
});

it('invalidateCache uses Cache::forget when tags not supported', function (): void {
    Cache::shouldReceive('supportsTags')->once()->andReturn(false);
    Cache::shouldReceive('forget')->once()->with('test_table');

    $model = new class extends Model
    {
        use HasCache;

        protected $table = 'test_table';
    };

    $model->invalidateCache();
});
