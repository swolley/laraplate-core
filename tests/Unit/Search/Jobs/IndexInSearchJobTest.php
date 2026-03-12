<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Jobs\IndexInSearchJob;
use Modules\Core\Tests\Fixtures\StubSearchableModel;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    config(['scout.queue.queue' => 'indexing', 'scout.queue.tries' => 3, 'scout.queue.timeout' => 120, 'scout.queue.backoff' => [30, 60, 180]]);
});

it('accepts model that uses Searchable trait', function (): void {
    $model = new StubSearchableModel();
    $job = new IndexInSearchJob($model);
    expect($job)->toBeInstanceOf(IndexInSearchJob::class)
        ->and($job->queue)->toBe('indexing')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(120)
        ->and($job->backoff)->toBe([30, 60, 180]);
});

it('throws when model does not use Searchable', function (): void {
    $plain = new class extends Model
    {
        protected $table = 'users';
    };
    expect(fn () => new IndexInSearchJob($plain))
        ->toThrow(\InvalidArgumentException::class, 'does not implement the Searchable trait');
});

it('returns middleware with RateLimited', function (): void {
    $job = new IndexInSearchJob(new StubSearchableModel());
    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(\Illuminate\Queue\Middleware\RateLimited::class);
});

