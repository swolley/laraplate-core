<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Events\ModelsRequireIndexing;
use Modules\Core\Tests\Stubs\Search\BulkSearchableStubModel;
use Modules\Core\Tests\Stubs\Search\RecordingSearchEngineStub;

/**
 * @return Illuminate\Support\Collection<int, BulkSearchableStubModel>
 */
function bulk_stub_models(RecordingSearchEngineStub $engine, int $count): Illuminate\Support\Collection
{
    return collect(range(1, $count))->map(
        fn (int $i): BulkSearchableStubModel => (new BulkSearchableStubModel)
            ->withEngine($engine)
            ->forceFill(['id' => $i]),
    );
}

it('takes the batched path for an async multi-model import', function (): void {
    config(['scout.queue' => true, 'core.bulk_index_batch' => 2]);
    Event::fake([ModelsRequireIndexing::class, ModelRequiresIndexing::class]);

    $engine = new RecordingSearchEngineStub;
    $models = bulk_stub_models($engine, 5);

    $models->first()->queueMakeSearchable($models);

    // One batch pre-process event for the whole chunk, no per-model fan-out.
    Event::assertDispatched(
        ModelsRequireIndexing::class,
        fn (ModelsRequireIndexing $event): bool => $event->models->count() === 5 && $event->sync === true,
    );
    Event::assertNotDispatched(ModelRequiresIndexing::class);

    // Engine written in adaptive batches capped at bulk_index_batch, covering all models.
    expect($engine->update_calls)->toBe(3)
        ->and($engine->batch_sizes)->toBe([2, 2, 1])
        ->and(array_sum($engine->batch_sizes))->toBe(5);
});

it('keeps the per-model event path for a single async save', function (): void {
    config(['scout.queue' => true, 'core.bulk_index_batch' => 2]);
    Event::fake([ModelsRequireIndexing::class, ModelRequiresIndexing::class]);

    $engine = new RecordingSearchEngineStub;
    $model = bulk_stub_models($engine, 1)->first();

    $model->queueMakeSearchable(collect([$model]));

    Event::assertDispatched(ModelRequiresIndexing::class);
    Event::assertNotDispatched(ModelsRequireIndexing::class);
    // No bulk write: the finalize listener path drives IndexInSearchJob per model.
    expect($engine->update_calls)->toBe(0);
});
