<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Modules\Core\Search\Jobs\IndexDeferredSearchChunkJob;
use Modules\Core\Search\Jobs\Middleware\FailWhenSearchEngineUnreachable;
use Modules\Core\Support\SearchEngineAvailability;
use Modules\Core\Tests\Stubs\Search\DegradingSearchableStubModel;
use Modules\Core\Tests\Stubs\Search\DegradingSearchEngineStub;
use Modules\Core\Tests\Stubs\Search\UnreachableNetworkException;

function degrading_stub_model(Throwable $failure): DegradingSearchableStubModel
{
    $model = new DegradingSearchableStubModel;
    $model->setAttribute('id', 1);

    return $model->withEngine(new DegradingSearchEngineStub($failure));
}

it('recognises transport failures as an unreachable engine', function (): void {
    expect(SearchEngineAvailability::isUnreachable(new UnreachableNetworkException))->toBeTrue()
        ->and(SearchEngineAvailability::isUnreachable(new ConnectionException('refused')))->toBeTrue()
        ->and(SearchEngineAvailability::isUnreachable(new RuntimeException('wrapped', 0, new UnreachableNetworkException)))->toBeTrue();
});

it('does not treat an indexing error as an unreachable engine', function (): void {
    expect(SearchEngineAvailability::isUnreachable(new RuntimeException('field mapping is invalid')))->toBeFalse();
});

it('keeps the write path alive when the search engine cannot be reached', function (): void {
    config(['scout.queue' => false]);

    Log::shouldReceive('warning')
        ->atLeast()
        ->once()
        ->withArgs(static fn (string $message): bool => str_contains($message, 'Search engine unreachable'));
    Log::shouldReceive('debug')->zeroOrMoreTimes();
    Log::shouldReceive('error')->zeroOrMoreTimes();
    Log::shouldReceive('info')->zeroOrMoreTimes();

    $model = degrading_stub_model(new UnreachableNetworkException);

    $model->searchable();

    expect($model->engine->index_checks)->toBeGreaterThan(0);
});

it('still reports an indexing failure that is not a transport error', function (): void {
    config(['scout.queue' => false]);

    $model = degrading_stub_model(new RuntimeException('field mapping is invalid'));

    expect(static fn () => $model->searchable())
        ->toThrow(RuntimeException::class, 'field mapping is invalid');
});

it('rethrows an unreachable engine inside a strict scope, so a queued job fails instead of skipping', function (): void {
    config(['scout.queue' => false]);

    $model = degrading_stub_model(new UnreachableNetworkException);

    expect(static fn () => SearchEngineAvailability::strictly(static fn () => $model->searchable()))
        ->toThrow(UnreachableNetworkException::class);
});

it('leaves the strict scope when it ends, even when it ends with an exception', function (): void {
    expect(SearchEngineAvailability::isStrict())->toBeFalse();

    try {
        SearchEngineAvailability::strictly(static function (): void {
            expect(SearchEngineAvailability::isStrict())->toBeTrue();

            throw new RuntimeException('inside');
        });
    } catch (RuntimeException) {
    }

    expect(SearchEngineAvailability::isStrict())->toBeFalse();
});

it('makes the job middleware turn an unreachable engine into a failure', function (): void {
    config(['scout.queue' => false]);

    $model = degrading_stub_model(new UnreachableNetworkException);

    expect(static fn () => (new FailWhenSearchEngineUnreachable)->handle(new stdClass, static fn (): mixed => $model->searchable()))
        ->toThrow(UnreachableNetworkException::class);
});

it('runs every queued search job under that middleware, ahead of the rate limiter', function (): void {
    $job = new IndexDeferredSearchChunkJob(DegradingSearchableStubModel::class, [1]);

    expect($job->middleware()[0])->toBeInstanceOf(FailWhenSearchEngineUnreachable::class);
});
