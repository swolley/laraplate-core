<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class IndexInElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    /**
     * Job timeout in seconds
     * 30s considering:
     * - Single indexing operation
     * - Elasticsearch usually on local network
     * - Buffer for network latency and retries
     */
    public $timeout = 30;

    /**
     * Maximum time to wait in queue before execution
     */
    public $maxExceptionsThenWait = 60;

    private string $reindex_key;

    public function __construct(
        private readonly object $model
    ) {
        $this->reindex_key = "elasticsearch:reindexing:{$this->model->searchableAs()}";
        $this->onQueue('indexing');
    }

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(10, 5),
            new RateLimited('indexing'),
        ];
    }

    public function handle(): void
    {
        try {
            $data = $this->model->toSearchableArray();
            $elasticsearch_client = ClientBuilder::create()->build();

            // Verifichiamo se è in corso una reindicizzazione
            $reindex_start = Redis::get($this->reindex_key);

            // Se c'è una reindicizzazione in corso e il documento è stato aggiornato prima
            // dell'inizio della reindicizzazione, saltiamo l'indicizzazione perché verrà
            // gestita dal processo di reindicizzazione
            if (
                $reindex_start &&
                $this->model->updated_at &&
                $this->model->updated_at < Carbon::parse($reindex_start)
            ) {
                return;
            }

            $elasticsearch_client->index([
                'index' => $this->model->searchableAs(),
                'id' => $this->model->id,
                'body' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Elasticsearch indexing failed for model: ' . $this->model::class, [
                'model_id' => $this->model->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Se l'errore è dovuto a un problema di mappatura, avviamo la reindicizzazione
            if ($this->isMappingError($e)) {
                \Log::info('Detected mapping error, triggering reindex', [
                    'model' => $this->model::class,
                    'index' => $this->model->searchableAs()
                ]);

                // Dispatchiamo il job di reindicizzazione
                ReindexElasticsearchJob::dispatch($this->model::class)
                    ->onQueue('indexing');

                // Non rilanciamo l'eccezione per evitare retry
                return;
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('IndexInElasticsearchJob failed', [
            'model' => $this->model::class,
            'model_id' => $this->model->id,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Determina se l'errore è dovuto a un problema di mappatura
     */
    private function isMappingError(\Exception $e): bool
    {
        $message = $e->getMessage();

        // Lista di possibili errori di mappatura di Elasticsearch
        $mapping_errors = [
            'mapper_parsing_exception',
            'illegal_argument_exception',
            'strict_dynamic_mapping_exception',
            'field_mapping_exception'
        ];

        foreach ($mapping_errors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        return false;
    }
}
