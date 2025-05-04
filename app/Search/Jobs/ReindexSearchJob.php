<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Laravel\Scout\Searchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Job for reindexing all models of a specified class
 * Supports both Elasticsearch and Typesense.
 */
final class ReindexSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts for the job.
     */
    public int $tries;

    /**
     * Job timeout in seconds.
     */
    public int $timeout;

    /**
     * Backoff time between attempts, in seconds.
     */
    public array $backoff;

    /**
     * Model class to reindex.
     */
    protected string $model_class;

    /**
     * Whether to use the bulk indexing.
     */
    protected bool $use_bulk;

    /**
     * Batch size for bulk indexing.
     */
    protected int $batch_size;

    /**
     * Constructor.
     *
     * @param  string  $model_class  Class name of models to reindex
     * @param  bool  $use_bulk  Whether to use bulk indexing
     * @param  int  $batch_size  Number of records to process in each batch (for bulk)
     */
    public function __construct(string $model_class, bool $use_bulk = true, int $batch_size = 500)
    {
        $this->model_class = $model_class;
        $this->use_bulk = $use_bulk;
        $this->batch_size = $batch_size;
        $this->onQueue(config('scout.queue_name', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue_tries', 3);
        $this->timeout = config('scout.queue_timeout', 600); // Longer timeout for reindexing
        $this->backoff = config('scout.queue_backoff', [60, 180, 360]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Validate the model class
        if (! class_exists($this->model_class)) {
            Log::error("Reindex job failed: Model class {$this->model_class} does not exist");

            return;
        }

        // Create an instance to check if it's searchable
        $model_instance = new $this->model_class();

        if (! in_array(Searchable::class, class_uses_recursive($this->model_class), true)) {
            Log::error("Reindex job failed: Model class {$this->model_class} does not use the Searchable trait");

            return;
        }

        $driver = config('scout.driver');
        $index_name = $model_instance->searchableAs();

        Log::info("Starting reindex job for {$this->model_class} with {$driver} driver", [
            'model' => $this->model_class,
            'index' => $index_name,
            'use_bulk' => $this->use_bulk,
            'batch_size' => $this->batch_size,
        ]);

        try {
            if ($this->use_bulk) {
                // Use the Scout searchable method which respects the batch size setting
                $model_instance::query()->searchable();
                Log::info('Bulk reindex job completed successfully', [
                    'model' => $this->model_class,
                    'index' => $index_name,
                ]);
            } else {
                // Process each model individually
                $count = 0;
                $model_instance::chunk($this->batch_size, function ($models) use (&$count): void {
                    foreach ($models as $model) {
                        // Instead of dispatching a job, call the searchable method directly
                        $model->searchable();
                        $count++;
                    }
                });

                Log::info('Individual reindex job completed successfully', [
                    'model' => $this->model_class,
                    'count' => $count,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error during reindex job', [
                'model' => $this->model_class,
                'driver' => $driver,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If there are attempts left, retry
            if ($this->tries > $this->attempts()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);

                return;
            }

            // If we've reached the maximum number of attempts, fail permanently
            $this->fail($e);
        }
    }
}
