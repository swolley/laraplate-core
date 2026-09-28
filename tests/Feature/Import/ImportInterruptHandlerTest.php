<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Events\ModelsRequireIndexing;
use Modules\Core\Import\Support\ImportInterruptHandler;
use Modules\Core\Search\DeferredSearchIndexing;
use Modules\Core\Search\Exceptions\DeferredRunInterruptedException;
use Modules\Core\Tests\Stubs\Search\DeferredSearchableStubModel;
use Modules\Core\Tests\Stubs\Search\RecordingSearchEngineStub;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(function (): void {
    DeferredSearchableStubModel::createTable();
    DeferredSearchableStubModel::$engine = new RecordingSearchEngineStub;
    config(['scout.queue' => true]);
});

afterEach(function (): void {
    DeferredSearchableStubModel::dropTable();
    DeferredSearchableStubModel::$engine = null;
});

/**
 * A handler whose operator always answers $choice, recording every question
 * and every exit instead of terminating the test process.
 *
 * @param-out  array{asked: int, exits: list<int>}  $calls
 */
function interruptHandlerAnswering(?string $choice, ?array &$calls): ImportInterruptHandler
{
    $calls = ['asked' => 0, 'exits' => []];

    return new ImportInterruptHandler(
        app(DeferredSearchIndexing::class),
        new BufferedOutput,
        $choice === null ? null : function () use ($choice, &$calls): string {
            $calls['asked']++;

            return $choice;
        },
        function (int $code) use (&$calls): void {
            $calls['exits'][] = $code;
        },
    );
}

it('quits at once without asking when nothing waits to be indexed', function (): void {
    $handler = interruptHandlerAnswering(ImportInterruptHandler::FINISH, $calls);

    $handler(SIGINT);

    expect($calls)->toBe(['asked' => 0, 'exits' => [130]]);
});

it('indexes the records imported so far, then stops, when the operator chooses to finish', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $handler = interruptHandlerAnswering(ImportInterruptHandler::FINISH, $calls);

    $run = fn (): mixed => app(DeferredSearchIndexing::class)->run(function () use ($handler): void {
        saveInterruptStub('a');
        saveInterruptStub('b');
        $handler(SIGINT);
        saveInterruptStub('never');
    }, batchSize: 10);

    expect($run)->toThrow(DeferredRunInterruptedException::class);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2]);
    expect(DeferredSearchableStubModel::query()->count())->toBe(2);
    expect($calls)->toBe(['asked' => 1, 'exits' => []]);
    expect($handler->exitCode())->toBe(130);
});

it('quits at once when the operator chooses to quit', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $handler = interruptHandlerAnswering(ImportInterruptHandler::QUIT, $calls);

    app(DeferredSearchIndexing::class)->run(function () use ($handler): void {
        saveInterruptStub('a');
        $handler(SIGINT);
    }, batchSize: 10);

    expect($calls)->toBe(['asked' => 1, 'exits' => [130]]);
});

it('resumes the import when the operator chooses to resume', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $handler = interruptHandlerAnswering(ImportInterruptHandler::RESUME, $calls);

    app(DeferredSearchIndexing::class)->run(function () use ($handler): void {
        saveInterruptStub('a');
        $handler(SIGINT);
        saveInterruptStub('b');
    }, batchSize: 10);

    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([2]);
    expect($calls)->toBe(['asked' => 1, 'exits' => []]);
});

it('indexes and stops without asking on SIGTERM or when it cannot ask', function (int $signal, ?string $choice, int $exitCode): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $handler = interruptHandlerAnswering($choice, $calls);

    $run = fn (): mixed => app(DeferredSearchIndexing::class)->run(function () use ($handler, $signal): void {
        saveInterruptStub('a');
        $handler($signal);
    }, batchSize: 10);

    expect($run)->toThrow(DeferredRunInterruptedException::class);
    expect(DeferredSearchableStubModel::$engine->batch_sizes)->toBe([1]);
    expect($calls)->toBe(['asked' => 0, 'exits' => []]);
    expect($handler->exitCode())->toBe($exitCode);
})->with([
    'SIGTERM' => [SIGTERM, ImportInterruptHandler::QUIT, 143],
    'no terminal' => [SIGINT, null, 130],
]);

it('quits at once on a second signal while indexing before quitting', function (): void {
    Event::fake([ModelRequiresIndexing::class, ModelsRequireIndexing::class]);
    $handler = interruptHandlerAnswering(ImportInterruptHandler::FINISH, $calls);
    DeferredSearchableStubModel::$engine->onUpdate = static function () use ($handler): void {
        $handler(SIGINT);
    };

    $run = fn (): mixed => app(DeferredSearchIndexing::class)->run(function () use ($handler): void {
        saveInterruptStub('a');
        $handler(SIGINT);
    }, batchSize: 10);

    expect($run)->toThrow(DeferredRunInterruptedException::class);
    expect($calls)->toBe(['asked' => 1, 'exits' => [130]]);
});

function saveInterruptStub(string $name): DeferredSearchableStubModel
{
    return DeferredSearchableStubModel::query()->create(['name' => $name]);
}
