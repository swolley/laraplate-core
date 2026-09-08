<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;
use Modules\Core\Events\ModelPreProcessingCompleted;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Search\Jobs\IndexInSearchJob;

final class FinalizeModelIndexingListener
{
    public function handle(ModelPreProcessingCompleted $event): void
    {
        $cache_key = $this->getCacheKey($event->model);

        // Retrieve the original event from cache
        $indexing_event = Cache::get($cache_key);

        if (! $indexing_event instanceof ModelRequiresIndexing) {
            // The coordination event expired (10-minute TTL) or was already
            // consumed. A pre-processing step still completed for this model
            // (e.g. a late manual retry of an embedding job), so index it now
            // rather than dropping the work silently.
            $this->dispatchFallbackIndexing($event->model);

            return;
        }

        // Mark this pre-processing as completed
        $indexing_event->markPreProcessingCompleted($event->processing_type);

        // Check if all pre-processing are completed
        if ($indexing_event->allPreProcessingCompleted()) {
            // All completed, dispatch indexing
            if ($indexing_event->sync) {
                new IndexInSearchJob($indexing_event->model)->handle();
            } else {
                dispatch(new IndexInSearchJob($indexing_event->model));
            }

            // Remove from cache
            Cache::forget($cache_key);
        } else {
            // Not all completed, save updated event in cache
            Cache::put($cache_key, $indexing_event, now()->addMinutes(10));
        }
    }

    private function dispatchFallbackIndexing(Model $model): void
    {
        // IndexInSearchJob only accepts models that are searchable; guard here so
        // a stray completion event for a non-searchable model is a no-op instead
        // of throwing.
        if (! in_array(Searchable::class, class_uses_recursive($model::class), true)) {
            return;
        }

        dispatch(new IndexInSearchJob($model));
    }

    private function getCacheKey(Model $model): string
    {
        return "model_indexing:{$model->getTable()}:{$model->getKey()}";
    }
}
