<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
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
final class IndexInSearchJob extends CommonSearchJob
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
     * @param  Model  $model  The model to index
     */
    public function __construct(
        private Model $model,
    ) {
        // Validate that the model implements Searchable
        throw_unless(in_array(Searchable::class, class_uses_recursive($model::class), true), InvalidArgumentException::class, 'Model ' . $model::class . ' does not implement the Searchable trait');

        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $driver = config('scout.driver');

        /** @phpstan-ignore method.notFound */
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
        } catch (Exception $exception) {
            $this->handleIndexingException($exception, $document_id, $index_name, $driver);
        }
    }

    private function logIndexingStart(string $document_id, string $index_name, string $driver): void
    {
        Log::debug(sprintf('Indexing document %s in %s using %s driver', $document_id, $index_name, $driver));
    }

    private function shouldDeleteDocument(): bool
    {
        return method_exists($this->model, 'shouldBeSearchable') && ! $this->model->shouldBeSearchable();
    }

    private function deleteDocument(): void
    {
        /** @phpstan-ignore method.notFound */
        $this->model->unsearchable();
    }

    private function updateDocument(): void
    {
        /** @phpstan-ignore method.notFound */
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
        Log::debug(sprintf('Document %s successfully indexed in %s', $document_id, $index_name));
    }

    private function handleIndexingException(Exception $e, string $document_id, string $index_name, string $driver): void
    {
        Log::error(sprintf('Error indexing document %s in %s', $document_id, $index_name), [
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
