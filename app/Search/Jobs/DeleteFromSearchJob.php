<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\ElasticsearchService;
use Override;

/**
 * Job for deleting a document from a search index
 * Supports both Elasticsearch and Typesense.
 */
final class DeleteFromSearchJob extends CommonSearchJob
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
    #[Override]
    public function __construct(private string $index, private string|int $document_id)
    {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $driver = config('scout.driver');
            Log::debug(sprintf('Deleting document %s from %s using %s driver', $this->document_id, $this->index, $driver));

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
                Log::warning(sprintf('Document deletion failed, document %s not found or other error', $this->document_id));
            }
        } catch (Exception $exception) {
            Log::error('Error deleting document ' . $this->document_id, [
                'index' => $this->index,
                'driver' => config('scout.driver'),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // If there are attempts left, retry
            if ($this->tries > $this->attempts()) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);

                return;
            }

            // If we've reached the maximum number of attempts, fail permanently
            $this->fail($exception);
        }
    }
}
