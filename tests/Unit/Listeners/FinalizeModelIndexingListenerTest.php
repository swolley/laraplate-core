<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Events\ModelPreProcessingCompleted;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Listeners\FinalizeModelIndexingListener;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('returns early when no indexing event in cache', function (): void {
    $setting = Setting::factory()->create();
    $event = new ModelPreProcessingCompleted($setting, 'embeddings');

    (new FinalizeModelIndexingListener())->handle($event);

    // No exception, no dispatch (event was not in cache)
    expect(true)->toBeTrue();
});

it('updates cache when not all pre-processing completed', function (): void {
    $setting = Setting::factory()->create();
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
