<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
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
