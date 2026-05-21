<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Cache\HasCache;


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

it('invalidateCache uses tags flush when repository exposes getCacheTags', function (): void {
    $fake_cache = new class
    {
        public array $tags_calls = [];

        public array $forget_calls = [];

        public function supportsTags(): bool
        {
            return true;
        }

        public function store(): object
        {
            return new class
            {
                public function getCacheTags(string $table): array
                {
                    return [$table];
                }
            };
        }

        public function tags(array $tags): object
        {
            $this->tags_calls[] = $tags;

            return new class
            {
                public function flush(): bool
                {
                    return true;
                }
            };
        }

        public function forget(string $key): bool
        {
            $this->forget_calls[] = $key;

            return true;
        }
    };
    Cache::swap($fake_cache);

    $model = new class extends Model
    {
        use HasCache;

        protected $table = 'test_table';
    };

    $model->invalidateCache();

    expect($fake_cache->tags_calls)->toContain(['test_table'])
        ->and($fake_cache->forget_calls)->toBe([]);
});

it('invalidateCache falls back to forget when tags are supported but repository has no getCacheTags', function (): void {
    $fake_cache = new class
    {
        public array $forget_calls = [];

        public function supportsTags(): bool
        {
            return true;
        }

        public function store(): object
        {
            return new stdClass();
        }

        public function forget(string $key): bool
        {
            $this->forget_calls[] = $key;

            return true;
        }
    };
    Cache::swap($fake_cache);

    $model = new class extends Model
    {
        use HasCache;

        protected $table = 'test_table';
    };

    $model->invalidateCache();

    expect($fake_cache->forget_calls)->toContain('test_table');
});

it('bootHasCache invalidates cache on delete event', function (): void {
    Schema::create('cache_test_models', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    Cache::shouldReceive('supportsTags')->atLeast()->once()->andReturn(false);
    Cache::shouldReceive('forget')->atLeast()->once()->with('cache_test_models');

    $model = new class extends Model
    {
        use HasCache;

        public $timestamps = false;

        protected $table = 'cache_test_models';

        protected $guarded = [];
    };

    $instance = $model::query()->create(['name' => 'to-delete']);
    $instance->delete();

    Schema::dropIfExists('cache_test_models');
    expect(true)->toBeTrue();
});
