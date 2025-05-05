<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Core\Services\ElasticsearchService;

/**
 * Job for deleting a document from a search index
 * Supports both Elasticsearch and Typesense.
 */
final class DeleteFromSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts.
     */
    public int $tries;

    /**
     * Job timeout in seconds.
     */
    public int $timeout;

    /**
     * Backoff time between retries (in seconds).
     */
    public array $backoff;

    /**
     * Create a new job instance.
     *
     * @param  string  $index  Search index name
     * @param  string|int  $document_id  Document ID to delete
     */
    public function __construct(private string $index, private string|int $document_id)
    {
        $this->onQueue(config('scout.queue_name', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue_tries', 3);
        $this->timeout = config('scout.queue_timeout', 60);
        $this->backoff = config('scout.queue_backoff', [30, 60, 120]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $driver = config('scout.driver');
            Log::debug("Deleting document {$this->document_id} from {$this->index} using {$driver} driver");

            $success = false;

            if ($driver === 'elastic') {
                // For Elasticsearch, use the service
                $service = ElasticsearchService::getInstance();
                $success = $service->deleteDocument($this->index, $this->document_id, true);
            } elseif ($driver === 'typesense') {
                // For Typesense, use the client directly
                try {
                    $client = app('typesense');
                    $client->collections[$this->index]->documents[$this->document_id]->delete();
                    $success = true;
                } catch (Exception $e) {
                    // Document not found is not an error
                    if (mb_strpos($e->getMessage(), 'Not Found') !== false || $e->getCode() === 404) {
                        $success = false;
                    } else {
                        throw $e;
                    }
                }
            }

            if ($success) {
                Log::debug('Document deletion completed successfully');
            } else {
                Log::warning("Document deletion failed, document {$this->document_id} not found or other error");
            }
        } catch (Exception $e) {
            Log::error("Error deleting document {$this->document_id}", [
                'index' => $this->index,
                'driver' => config('scout.driver'),
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
