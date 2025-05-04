<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Throwable;
use Illuminate\Bus\Queueable;
use InvalidArgumentException;
use Laravel\Scout\Searchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Job for bulk indexing documents in search engines
 * Supports both Elasticsearch and Typesense via Laravel Scout.
 */
final class BulkIndexSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of job attempts.
     */
    public int $tries;

    /**
     * Job timeout in seconds.
     */
    public int $timeout;

    /**
     * Backoff time between attempts (in seconds).
     */
    public array $backoff;

    /**
     * Create a new job instance.
     *
     * @param  Collection  $models  Collection of models to index
     * @param  bool  $force  If true, forces indexing even if model shouldn't be searchable
     */
    public function __construct(
        protected Collection $models,
        protected bool $force = false,
    ) {
        // Validate that collection is not empty
        if ($models->isEmpty()) {
            throw new InvalidArgumentException('Cannot index an empty collection');
        }

        // Validate that all models are of the same class
        $model_class = $models->first()::class;

        if (! $models->every(fn ($model) => $model instanceof $model_class)) {
            throw new InvalidArgumentException('All models must be of the same class');
        }

        // Validate that the model implements Searchable
        if (! $this->force && ! in_array(Searchable::class, class_uses_recursive($model_class), true)) {
            throw new InvalidArgumentException("Model {$model_class} does not use the Searchable trait");
        }

        $this->onQueue(config('scout.queue_name', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue_tries', 3);
        $this->timeout = config('scout.queue_timeout', 120);
        $this->backoff = config('scout.queue_backoff', [30, 60, 180]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $model_class = $this->models->first()::class;
            $driver = config('scout.driver');

            Log::info('Starting bulk index job', [
                'model' => $model_class,
                'count' => $this->models->count(),
                'driver' => $driver,
            ]);

            // Use the searchable method which will respect the Scout driver configuration
            $this->models->searchable();

            Log::info('Bulk index job completed', [
                'model' => $model_class,
                'count' => $this->models->count(),
                'driver' => $driver,
            ]);
        } catch (Exception $e) {
            Log::error('Error in bulk index job', [
                'model' => $this->models->first()::class,
                'count' => $this->models->count(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If there are attempts left, retry
            if ($this->tries > $this->attempts()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);

                return;
            }

            // If we've reached maximum attempts, fail permanently
            $this->fail($e);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        $model_class = $this->models->first()::class;
        $count = $this->models->count();

        Log::error('Bulk indexing job failed', [
            'model_class' => $model_class,
            'count' => $count,
            'driver' => config('scout.driver'),
            'error' => $exception->getMessage(),
        ]);
    }
}
