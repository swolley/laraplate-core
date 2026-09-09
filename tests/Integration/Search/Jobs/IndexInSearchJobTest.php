<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Core\Search\Jobs\IndexInSearchJob;
use Modules\Core\Tests\Fixtures\StubSearchableModel;
use Modules\Core\Tests\Stubs\Search\IndexInSearchModelWithoutTimestamp;
use Modules\Core\Tests\Stubs\Search\IndexInSearchModelWithTimestamp;

beforeEach(function (): void {
    config(['scout.queue.queue' => 'indexing', 'scout.queue.tries' => 3, 'scout.queue.timeout' => 120, 'scout.queue.backoff' => [30, 60, 180]]);
});

it('accepts model that uses Searchable trait', function (): void {
    $model = new StubSearchableModel;
    $job = new IndexInSearchJob($model);
    expect($job)->toBeInstanceOf(IndexInSearchJob::class)
        ->and($job->queue)->toBe('indexing')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->backoff)->toBe([30, 60, 180])
        ->and($job->maxExceptions)->toBe(3);
});

it('uses a future time-based retryUntil so rate-limit releases do not kill the job', function (): void {
    $job = new IndexInSearchJob(new StubSearchableModel);

    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->retryUntil()->getTimestamp())->toBeGreaterThan(now()->getTimestamp());
});

it('throws when model does not use Searchable', function (): void {
    $plain = new class extends Model
    {
        protected $table = 'users';
    };
    expect(fn () => new IndexInSearchJob($plain))
        ->toThrow(InvalidArgumentException::class, 'does not implement the Searchable trait');
});

it('returns middleware with RateLimited', function (): void {
    $job = new IndexInSearchJob(new StubSearchableModel);
    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(Illuminate\Queue\Middleware\RateLimited::class);
});

it('deletes document when model should not be searchable', function (): void {
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithoutTimestamp;
    $model->should_be_searchable = false;
    Log::spy();
    $job = new IndexInSearchJob($model);

    $job->handle();

    expect($model->unsearchable_called)->toBeTrue()
        ->and($model->engine->updated)->toBeFalse();
    Log::shouldHaveReceived('debug')->atLeast()->once();
});

it('updates document and timestamp when indexing succeeds', function (): void {
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithTimestamp;
    Log::spy();
    $job = new IndexInSearchJob($model);

    $job->handle();

    expect($model->engine->updated)->toBeTrue()
        ->and($model->timestamp_updated)->toBeTrue();
    Log::shouldHaveReceived('debug')->atLeast()->times(2);
});

it('passes a collection (not a bare model) to the engine update', function (): void {
    // Regression: Scout engines call ->isEmpty() on the argument; passing a
    // single model threw "Content::isEmpty()" and failed every indexing job.
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithTimestamp;
    $job = new IndexInSearchJob($model);

    $job->handle();

    expect($model->engine->received_collection)->toBeTrue();
});

it('updates document without timestamp method when indexing succeeds', function (): void {
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithoutTimestamp;
    Log::spy();
    $job = new IndexInSearchJob($model);

    $job->handle();

    expect($model->engine->updated)->toBeTrue();
    Log::shouldHaveReceived('debug')->atLeast()->times(2);
});

it('rethrows indexing exceptions so the worker applies backoff and maxExceptions', function (): void {
    // The job no longer decides release/fail from attempts() (rate-limit releases
    // inflate it); it logs and rethrows, letting the worker apply $backoff and
    // fail only after $maxExceptions real errors or once retryUntil() elapses.
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithoutTimestamp;
    $model->engine->throw_on_update = true;
    Log::spy();
    $job = new IndexInSearchJob($model);

    expect(fn (): mixed => $job->handle())->toThrow(Exception::class);
    Log::shouldHaveReceived('error')->once();
});
