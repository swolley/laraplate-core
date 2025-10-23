<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;
use Override;

/**
 * Job for reindexing all models of a specified class
 * Supports both Elasticsearch and Typesense.
 */
final class ReindexSearchJob extends CommonSearchJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
     * Constructor.
     *
     * @param  string  $model_class  Class name of models to reindex
     * @param  bool  $use_bulk  Whether to use bulk indexing
     * @param  int  $batch_size  Number of records to process in each batch (for bulk)
     */
    #[Override]
    public function __construct(private string $model_class, private bool $use_bulk = true, private int $batch_size = 500)
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (! $this->isModelClassValid()) {
            $this->logModelClassError();

            return;
        }

        $model_instance = new $this->model_class();

        if (! $this->isModelSearchable()) {
            $this->logModelNotSearchableError();

            return;
        }

        $driver = config('scout.driver');
        $index_name = $model_instance->searchableAs();

        $this->logReindexStart($driver, $index_name);

        try {
            if ($this->use_bulk) {
                $this->bulkReindex($model_instance, $index_name);
            } else {
                $this->individualReindex($model_instance);
            }
        } catch (Exception $exception) {
            $this->handleReindexException($exception, $driver);
        }
    }

    private function isModelClassValid(): bool
    {
        return class_exists($this->model_class);
    }

    private function logModelClassError(): void
    {
        Log::error(sprintf('Reindex job failed: Model class %s does not exist', $this->model_class));
    }

    private function isModelSearchable(): bool
    {
        return in_array(Searchable::class, class_uses_recursive($this->model_class), true);
    }

    private function logModelNotSearchableError(): void
    {
        Log::error(sprintf('Reindex job failed: Model class %s does not use the Searchable trait', $this->model_class));
    }

    private function logReindexStart(string $driver, string $index_name): void
    {
        Log::info(sprintf('Starting reindex job for %s with %s driver', $this->model_class, $driver), [
            'model' => $this->model_class,
            'index' => $index_name,
            'use_bulk' => $this->use_bulk,
            'batch_size' => $this->batch_size,
        ]);
    }

    private function bulkReindex(object $model_instance, string $index_name): void
    {
        $model_instance::query()->searchable();
        Log::info('Bulk reindex job completed successfully', [
            'model' => $this->model_class,
            'index' => $index_name,
        ]);
    }

    private function individualReindex(object $model_instance): void
    {
        $count = 0;
        $model_instance::chunk($this->batch_size, function ($models) use (&$count): void {
            foreach ($models as $model) {
                $model->searchable();
                $count++;
            }
        });

        Log::info('Individual reindex job completed successfully', [
            'model' => $this->model_class,
            'count' => $count,
        ]);
    }

    private function handleReindexException(Exception $e, string $driver): void
    {
        Log::error('Error during reindex job', [
            'model' => $this->model_class,
            'driver' => $driver,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($this->tries > $this->attempts()) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);

            return;
        }

        $this->fail($e);
    }
}
