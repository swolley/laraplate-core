<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Core\Events\ModelPreProcessingCompleted;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Listeners\FinalizeModelIndexingListener;
use Modules\Core\Models\Setting;
use Modules\Core\Search\Jobs\IndexInSearchJob;

it('does not dispatch a fallback for a non-searchable model on cache miss', function (): void {
    Queue::fake();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();
    $event = new ModelPreProcessingCompleted($setting, 'embeddings');

    (new FinalizeModelIndexingListener())->handle($event);

    // Setting is not searchable, so nothing to index; no exception, no dispatch.
    Queue::assertNotPushed(IndexInSearchJob::class);
});

it('dispatches indexing on cache miss for a searchable model (late-retry recovery)', function (): void {
    // When the coordination event has expired from cache (e.g. a manual retry of
    // a pre-processing job hours later), the regenerated data must still reach the
    // search engine instead of being silently dropped.
    Queue::fake();

    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use Laravel\Scout\Searchable;

        public $timestamps = false;

        protected $table = 'fallback_index_models';

        public function getKey()
        {
            return 11;
        }

        public function searchableAs(): string
        {
            return 'fallback_index_models';
        }

        public function shouldBeSearchable(): bool
        {
            return false;
        }

        public function unsearchable()
        {
            return null;
        }
    };

    // No cache entry for this model -> fallback path.
    (new FinalizeModelIndexingListener())->handle(new ModelPreProcessingCompleted($model, 'embeddings'));

    Queue::assertPushed(IndexInSearchJob::class);
});

it('updates cache when not all pre-processing completed', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();
    $indexing_event = new ModelRequiresIndexing($setting, false);
    $indexing_event->addRequiredPreProcessing('embeddings');
    $indexing_event->addRequiredPreProcessing('translation');
    $indexing_event->markPreProcessingCompleted('embeddings');
    $cache_key = "model_indexing:{$setting->getTable()}:{$setting->getKey()}";
    Cache::put($cache_key, $indexing_event, now()->addMinutes(10));

    $event = new ModelPreProcessingCompleted($setting, 'embeddings');
    (new FinalizeModelIndexingListener())->handle($event);

    // After handle, only embeddings is completed; listener puts updated event back in cache
    $cached = Cache::get($cache_key);
    expect($cached)->toBeInstanceOf(ModelRequiresIndexing::class)
        ->and($cached->completed_pre_processing)->toContain('embeddings');
});

it('finalizes indexing and forgets cache when sync preprocessing completes', function (): void {
    Log::spy();
    config()->set('scout.driver', 'database');

    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use Laravel\Scout\Searchable;

        public $timestamps = false;

        protected $table = 'sync_index_models';

        public function getKey()
        {
            return 7;
        }

        public function searchableAs(): string
        {
            return 'sync_index_models';
        }

        public function shouldBeSearchable(): bool
        {
            return false;
        }

        public function unsearchable()
        {
            return null;
        }
    };

    $indexing_event = new ModelRequiresIndexing($model, true);
    $indexing_event->addRequiredPreProcessing('embeddings');
    $cache_key = "model_indexing:{$model->getTable()}:{$model->getKey()}";
    Cache::put($cache_key, $indexing_event, now()->addMinutes(10));

    $event = new ModelPreProcessingCompleted($model, 'embeddings');
    (new FinalizeModelIndexingListener())->handle($event);

    expect(Cache::get($cache_key))->toBeNull();
});

it('dispatches async indexing job and forgets cache when preprocessing completes', function (): void {
    Queue::fake();

    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use Laravel\Scout\Searchable;

        public $timestamps = false;

        protected $table = 'async_index_models';

        public function getKey()
        {
            return 9;
        }

        public function searchableAs(): string
        {
            return 'async_index_models';
        }

        public function shouldBeSearchable(): bool
        {
            return false;
        }

        public function unsearchable()
        {
            return null;
        }
    };

    $indexing_event = new ModelRequiresIndexing($model, false);
    $indexing_event->addRequiredPreProcessing('embeddings');
    $cache_key = "model_indexing:{$model->getTable()}:{$model->getKey()}";
    Cache::put($cache_key, $indexing_event, now()->addMinutes(10));

    (new FinalizeModelIndexingListener())->handle(new ModelPreProcessingCompleted($model, 'embeddings'));

    Queue::assertPushed(IndexInSearchJob::class);
    expect(Cache::get($cache_key))->toBeNull();
});
