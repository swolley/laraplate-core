<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Events\ModelsRequireIndexing;
use Modules\Core\Search\DeferredSearchIndexing;
use Modules\Core\Search\Exceptions\DeferredRunInterruptedException;
use Modules\Core\Tests\Stubs\Search\DeferredSearchableStubModel;
use Modules\Core\Tests\Stubs\Search\RecordingSearchEngineStub;

beforeEach(function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
});

afterEach(function (): void {
    DeferredSearchableStubModel::dropTable();
    DeferredSearchableStubModel::$engine = null;
});

function saveDeferredStub(string $name): DeferredSearchableStubModel
{
    return DeferredSearchableStubModel::query()->create(['name' => $name]);
}

it('keeps the real-time path outside a run', function (): void {
    Event::fake([ModelRequiresIndexing::class]);

    saveDeferredStub('live');

    Event::assertDispatched(ModelRequiresIndexing::class);
    expect(app(DeferredSearchIndexing::class)->defer([]))->toBeFalse();
});

it('indexes every record saved during a run once, in one bulk flush at the end', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);

    app(DeferredSearchIndexing::class)->run(function (): void {
        saveDeferredStub('a')->update(['name' => 'a2']);
        saveDeferredStub('b');

        expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
    }, batchSize: 10);

    Event::assertNotDispatched(ModelRequiresIndexing::class);
    Event::assertDispatchedTimes(ModelsRequireIndexing::class, 1);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2]);
});

it('flushes every batch size distinct records', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);

    app(DeferredSearchIndexing::class)->run(function (): void {
        foreach (range(1, 5) as $index) {
            saveDeferredStub("row-{$index}");
        }
    }, batchSize: 2);

    Event::assertDispatchedTimes(ModelsRequireIndexing::class, 3);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 2, 1]);
});

it('waits for the open transaction to commit before a threshold flush', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $indexing = app(DeferredSearchIndexing::class);

    $indexing->run(function () use ($indexing): void {
        DB::transaction(function () use ($indexing): void {
            DB::table(DeferredSearchableStubModel::TABLE)->insert([['name' => 'a'], ['name' => 'b'], ['name' => 'c']]);
            $indexing->defer(DeferredSearchableStubModel::query()->get());

            expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
        });

        expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 1]);
    }, batchSize: 2);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 1]);
});

it('skips recorded records whose rows are gone at flush time', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);

    app(DeferredSearchIndexing::class)->run(function (): void {
        saveDeferredStub('kept');
        saveDeferredStub('gone');
        DB::table(DeferredSearchableStubModel::TABLE)->where('name', 'gone')->delete();
    }, batchSize: 10);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
});

it('indexes and embeds nothing in discard mode', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);

    app(DeferredSearchIndexing::class)->run(function (): void {
        saveDeferredStub('a');
    }, batchSize: 1, discard: true);

    Event::assertNotDispatched(ModelRequiresIndexing::class);
    Event::assertNotDispatched(ModelsRequireIndexing::class);
    expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
});

it('flushes what was recorded before the callback failed, then rethrows', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $indexing = app(DeferredSearchIndexing::class);

    $run = fn (): mixed => $indexing->run(function (): void {
        saveDeferredStub('committed');

        throw new RuntimeException('source unavailable');
    }, batchSize: 10);

    expect($run)->toThrow(RuntimeException::class, 'source unavailable');
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
    expect($indexing->isDeferring())->toBeFalse();
});

it('lets a nested run join the outer one', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $indexing = app(DeferredSearchIndexing::class);

    $indexing->run(function () use ($indexing): void {
        $indexing->run(fn (): DeferredSearchableStubModel => saveDeferredStub('inner'), batchSize: 10);

        expect(DeferredSearchableStubModel::$engine->update_calls)->toBe(0);
    }, batchSize: 10);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
});

it('stops at once on interrupt and indexes what the run recorded', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $indexing = app(DeferredSearchIndexing::class);

    $run = fn (): mixed => $indexing->run(function () use ($indexing): void {
        saveDeferredStub('a');
        $indexing->interrupt();
        saveDeferredStub('never');
    }, batchSize: 10);

    expect($run)->toThrow(DeferredRunInterruptedException::class);
    expect(DeferredSearchableStubModel::query()->pluck('name')->all())->toBe(['a']);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
});

it('finishes the flush in progress on interrupt without losing a model its commit wrote', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $indexing = app(DeferredSearchIndexing::class);
    DeferredSearchableStubModel::$engine->onUpdate = static function () use ($indexing): void {
        $indexing->interrupt();
    };

    $run = fn (): mixed => $indexing->run(function (): void {
        DB::transaction(function (): void {
            saveDeferredStub('a');
            saveDeferredStub('b');
            saveDeferredStub('c');
        });
        saveDeferredStub('never');
    }, batchSize: 2);

    expect($run)->toThrow(DeferredRunInterruptedException::class);
    expect(DeferredSearchableStubModel::query()->count())->toBe(3);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2, 1]);
});
