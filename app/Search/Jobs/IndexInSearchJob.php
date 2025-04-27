<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

/**
 * Job for indexing a model in search engines
 * Supports both Elasticsearch and Typesense via Laravel Scout
 */
class IndexInSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts for the job
     */
    public int $tries;

    /**
     * Job timeout in seconds
     */
    public int $timeout;

    /**
     * Backoff time between attempts, in seconds
     */
    public array $backoff;

    /**
     * Constructor
     * 
     * @param Model $model The model to index
     */
    public function __construct(
        protected Model $model
    ) {
        // Validate that the model implements Searchable
        if (!in_array(Searchable::class, class_uses_recursive(get_class($model)))) {
            throw new \InvalidArgumentException("Model " . get_class($model) . " does not implement the Searchable trait");
        }

        $this->onQueue(config('scout.queue_name', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue_tries', 3);
        $this->timeout = config('scout.queue_timeout', 60);
        $this->backoff = config('scout.queue_backoff', [2, 10, 30]);
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $driver = config('scout.driver');
        $index_name = $this->model->searchableAs();
        $document_id = $this->model->getKey();

        Log::debug("Indexing document {$document_id} in {$index_name} using {$driver} driver");

        // If the model implements shouldBeSearchable method and should not be searchable, delete the document
        if (method_exists($this->model, 'shouldBeSearchable') && !$this->model->shouldBeSearchable()) {
            $this->deleteDocument();
            return;
        }

        try {
            // Use the Scout searchable method to index the model
            // This automatically uses the correct driver (Elasticsearch or Typesense)
            $this->model->searchable();

            // Update indexing timestamp if the model supports that functionality
            if (method_exists($this->model, 'updateSearchIndexTimestamp')) {
                $this->model->updateSearchIndexTimestamp();
            }

            Log::debug("Document {$document_id} successfully indexed in {$index_name}");
        } catch (\Exception $e) {
            Log::error("Error indexing document {$document_id} in {$index_name}", [
                'driver' => $driver,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // If there are attempts left, retry
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                return;
            }

            // If we've reached the maximum number of attempts, fail permanently
            $this->fail($e);
        }
    }

    /**
     * Delete a document from the index
     */
    protected function deleteDocument(): void
    {
        $driver = config('scout.driver');
        $index_name = $this->model->searchableAs();
        $document_id = $this->model->getKey();

        try {
            // Use Scout's method to remove from index
            // This automatically uses the correct driver
            $this->model->unsearchable();

            Log::debug("Document {$document_id} successfully deleted from {$index_name}");
        } catch (\Exception $e) {
            Log::error("Error deleting document {$document_id} from {$index_name}", [
                'driver' => $driver,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
