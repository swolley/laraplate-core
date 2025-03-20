<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class ReindexElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // Non vogliamo retry automatici per questo job

    public $timeout = 3600; // 1 ora, considerando che potrebbe essere un'operazione lunga

    public $maxExceptionsThenWait = 60;

    private string $index_name;
    private string $reindex_key;

    public function __construct(
        private readonly string $model_class
    ) {
        // Creiamo un'istanza temporanea del modello per ottenere il nome dell'indice
        $model = new $model_class();
        $this->index_name = $model->searchableAs();
        $this->reindex_key = "elasticsearch:reindexing:{$this->index_name}";
        $this->onQueue('indexing');
    }

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(10, 5),
            new RateLimited('indexing'),
            // Preveniamo esecuzioni multiple dello stesso indice
            (new WithoutOverlapping($this->index_name))->expireAfter(3600),
        ];
    }

    public function handle(): void
    {
        try {
            $model = new $this->model_class();
            $elasticsearch_client = ClientBuilder::create()->build();

            // Salviamo il timestamp di inizio reindicizzazione
            $start_time = Carbon::now();
            Redis::set($this->reindex_key, $start_time->toDateTimeString());

            // Creiamo un indice temporaneo con la nuova struttura
            $temp_index = $this->index_name . '_temp_' . time();
            $new_index = [
                'index' => $temp_index,
                'body' => [
                    'mappings' => [
                        'properties' => $model->toSearchableIndex()
                    ]
                ]
            ];

            // Creiamo l'indice temporaneo
            $elasticsearch_client->indices()->create($new_index);

            // Recuperiamo tutti i documenti che devono essere indicizzati
            $chunks = $this->model_class::query()
                ->where('updated_at', '<', $start_time)
                ->cursor()
                ->chunk(100);

            // Creiamo un chain di jobs per l'indicizzazione
            $jobs = [];
            foreach ($chunks as $chunk) {
                $jobs[] = new BulkIndexElasticsearchJob($chunk, $temp_index);
            }

            // Eseguiamo i jobs in sequenza
            Bus::chain([
                ...$jobs,
                new FinalizeReindexJob($this->index_name, $temp_index, $this->reindex_key)
            ])->dispatch();
        } catch (\Exception $e) {
            // Cleanup in caso di errore
            Redis::del($this->reindex_key);

            if (
                isset($elasticsearch_client) && isset($temp_index) &&
                $elasticsearch_client->indices()->exists(['index' => $temp_index])
            ) {
                $elasticsearch_client->indices()->delete(['index' => $temp_index]);
            }

            \Log::error('Elasticsearch reindexing failed', [
                'model' => $this->model_class,
                'index' => $this->index_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Cleanup in caso di fallimento
        Redis::del($this->reindex_key);

        \Log::error('ReindexElasticsearchJob failed', [
            'model' => $this->model_class,
            'index' => $this->index_name,
            'error' => $exception->getMessage()
        ]);
    }
}
