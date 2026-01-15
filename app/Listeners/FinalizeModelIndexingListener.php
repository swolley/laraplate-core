<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Support\Facades\Cache;
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
            // Event not found, might be already processed or expired
            return;
        }

        // Mark this pre-processing as completed
        $indexing_event->markPreProcessingCompleted($event->processing_type);

        // Check if all pre-processing are completed
        if ($indexing_event->allPreProcessingCompleted()) {
            // All completed, dispatch indexing
            if ($indexing_event->sync) {
                (new IndexInSearchJob($indexing_event->model))->handle();
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

    private function getCacheKey($model): string
    {
        return "model_indexing:{$model->getTable()}:{$model->getKey()}";
    }
}
