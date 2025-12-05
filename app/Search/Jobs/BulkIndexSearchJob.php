<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Scout\Searchable;
use Throwable;

/**
 * Job for bulk indexing documents in search engines
 * Supports both Elasticsearch and Typesense via Laravel Scout.
 */
final class BulkIndexSearchJob extends CommonSearchJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
        private Collection $models,
        private bool $force = false,
    ) {
        // Validate that collection is not empty
        throw_if($models->isEmpty(), InvalidArgumentException::class, 'Cannot index an empty collection');

        // Validate that all models are of the same class
        $model_class = $models->first()::class;

        throw_unless($models->every(fn ($model): bool => $model instanceof $model_class), InvalidArgumentException::class, 'All models must be of the same class');

        // Validate that the model implements Searchable
        throw_if(! $this->force && ! in_array(Searchable::class, class_uses_recursive($model_class), true), InvalidArgumentException::class, sprintf('Model %s does not use the Searchable trait', $model_class));

        parent::__construct();
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
        } catch (Exception $exception) {
            Log::error('Error in bulk index job', [
                'model' => $this->models->first()::class,
                'count' => $this->models->count(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // If there are attempts left, retry
            if ($this->tries > $this->attempts()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);

                return;
            }

            // If we've reached maximum attempts, fail permanently
            $this->fail($exception);
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
