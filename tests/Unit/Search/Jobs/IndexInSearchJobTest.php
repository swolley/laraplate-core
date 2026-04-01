<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Modules\Core\Search\Jobs\IndexInSearchJob;
use Modules\Core\Tests\Fixtures\StubSearchableModel;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

final class IndexInSearchEngineFake
{
    public bool $updated = false;

    public bool $throw_on_update = false;

    public function update(Model $model): void
    {
        if ($this->throw_on_update) {
            throw new RuntimeException('forced update failure');
        }

        $this->updated = true;
    }
}

class IndexInSearchModelWithoutTimestamp extends Model
{
    use Searchable;

    public bool $unsearchable_called = false;

    public bool $should_be_searchable = true;

    public IndexInSearchEngineFake $engine;

    protected $table = 'settings';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->engine = new IndexInSearchEngineFake();
        $this->setAttribute($this->getKeyName(), 1);
    }

    public function searchableAs(): string
    {
        return 'settings';
    }

    public function searchableUsing(): IndexInSearchEngineFake
    {
        return $this->engine;
    }

    public function shouldBeSearchable(): bool
    {
        return $this->should_be_searchable;
    }

    public function unsearchable(): void
    {
        $this->unsearchable_called = true;
    }
}

final class IndexInSearchModelWithTimestamp extends IndexInSearchModelWithoutTimestamp
{
    public bool $timestamp_updated = false;

    public function updateSearchIndexTimestamp(): void
    {
        $this->timestamp_updated = true;
    }
}

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
        ->toThrow(InvalidArgumentException::class, 'does not implement the Searchable trait');
});

it('returns middleware with RateLimited', function (): void {
    $job = new IndexInSearchJob(new StubSearchableModel());
    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(Illuminate\Queue\Middleware\RateLimited::class);
});

it('deletes document when model should not be searchable', function (): void {
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithoutTimestamp();
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
    $model = new IndexInSearchModelWithTimestamp();
    Log::spy();
    $job = new IndexInSearchJob($model);

    $job->handle();

    expect($model->engine->updated)->toBeTrue()
        ->and($model->timestamp_updated)->toBeTrue();
    Log::shouldHaveReceived('debug')->atLeast()->times(2);
});

it('updates document without timestamp method when indexing succeeds', function (): void {
    config(['scout.driver' => 'typesense']);
    $model = new IndexInSearchModelWithoutTimestamp();
    Log::spy();
    $job = new IndexInSearchJob($model);

    $job->handle();

    expect($model->engine->updated)->toBeTrue();
    Log::shouldHaveReceived('debug')->atLeast()->times(2);
});

it('releases job on indexing exception when tries remain', function (): void {
    config(['scout.driver' => 'typesense', 'scout.queue.backoff' => [30, 60, 180], 'scout.queue.tries' => 3]);
    $model = new IndexInSearchModelWithoutTimestamp();
    $model->engine->throw_on_update = true;
    Log::spy();
    $job = (new IndexInSearchJob($model))->withFakeQueueInteractions();
    $job->job->attempts = 1;

    $job->handle();

    $job->assertReleased(30);
    $job->assertNotFailed();
    Log::shouldHaveReceived('error')->once();
});

it('fails job on indexing exception when no tries remain', function (): void {
    config(['scout.driver' => 'typesense', 'scout.queue.backoff' => [30, 60, 180], 'scout.queue.tries' => 3]);
    $model = new IndexInSearchModelWithoutTimestamp();
    $model->engine->throw_on_update = true;
    Log::spy();
    $job = (new IndexInSearchJob($model))->withFakeQueueInteractions();
    $job->job->attempts = 3;

    $job->handle();

    $job->assertFailed();
    $job->assertNotReleased();
    Log::shouldHaveReceived('error')->once();
});
