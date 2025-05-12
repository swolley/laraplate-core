<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Scout\Searchable;

/**
 * Job for indexing a model in search engines
 * Supports both Elasticsearch and Typesense via Laravel Scout.
 */
final class IndexInSearchJob implements ShouldQueue
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
     * Constructor.
     *
     * @param  Model  $model  The model to index
     */
    public function __construct(
        private Model $model,
    ) {
        // Validate that the model implements Searchable
        if (! in_array(Searchable::class, class_uses_recursive($model::class), true)) {
            throw new InvalidArgumentException('Model ' . $model::class . ' does not implement the Searchable trait');
        }

        $this->onQueue(config('scout.queue_name', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue_tries', 3);
        $this->timeout = config('scout.queue_timeout', 60);
        $this->backoff = config('scout.queue_backoff', [2, 10, 30]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $driver = config('scout.driver');
        $index_name = $this->model->searchableAs();
        $document_id = $this->model->getKey();

        $this->logIndexingStart($document_id, $index_name, $driver);

        if ($this->shouldDeleteDocument()) {
            $this->deleteDocument();

            return;
        }

        try {
            $this->updateDocument();
            $this->updateIndexTimestampIfNeeded();
            $this->logIndexingSuccess($document_id, $index_name);
        } catch (Exception $e) {
            $this->handleIndexingException($e, $document_id, $index_name, $driver);
        }
    }

    private function logIndexingStart(string $document_id, string $index_name, string $driver): void
    {
        Log::debug("Indexing document {$document_id} in {$index_name} using {$driver} driver");
    }

    private function shouldDeleteDocument(): bool
    {
        return method_exists($this->model, 'shouldBeSearchable') && ! $this->model->shouldBeSearchable();
    }

    private function deleteDocument(): void
    {
        $this->model->unsearchable();
    }

    private function updateDocument(): void
    {
        $this->model->searchableUsing()->update($this->model);
    }

    private function updateIndexTimestampIfNeeded(): void
    {
        if (method_exists($this->model, 'updateSearchIndexTimestamp')) {
            $this->model->updateSearchIndexTimestamp();
        }
    }

    private function logIndexingSuccess(string $document_id, string $index_name): void
    {
        Log::debug("Document {$document_id} successfully indexed in {$index_name}");
    }

    private function handleIndexingException(Exception $e, string $document_id, string $index_name, string $driver): void
    {
        Log::error("Error indexing document {$document_id} in {$index_name}", [
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

    /**
     * Delete a document from the index.
     */
    // protected function deleteDocument(): void
    // {
    //     $driver = config('scout.driver');
    //     $index_name = $this->model->searchableAs();
    //     $document_id = $this->model->getKey();

    //     try {
    //         // Use Scout's method to remove from index
    //         // This automatically uses the correct driver
    //         $this->model->unsearchable();

    //         Log::debug("Document {$document_id} successfully deleted from {$index_name}");
    //     } catch (\Exception $e) {
    //         Log::error("Error deleting document {$document_id} from {$index_name}", [
    //             'driver' => $driver,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         throw $e;
    //     }
    // }
}
