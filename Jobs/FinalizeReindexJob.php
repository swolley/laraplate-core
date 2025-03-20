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
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Redis;

class FinalizeReindexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1; // Non vogliamo retry automatici per questo job

    public $timeout = 300; // 5 minuti per finalizzare

    public $maxExceptionsThenWait = 60;

    public function __construct(
        private readonly string $index_name,
        private readonly string $temp_index,
        private readonly string $reindex_key
    ) {
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
            $elasticsearch_client = ClientBuilder::create()->build();

            // Verifichiamo che l'indice temporaneo esista
            if (!$elasticsearch_client->indices()->exists(['index' => $this->temp_index])) {
                throw new \Exception("Temporary index {$this->temp_index} does not exist");
            }

            // Se l'indice originale esiste, lo eliminiamo
            if ($elasticsearch_client->indices()->exists(['index' => $this->index_name])) {
                $elasticsearch_client->indices()->delete(['index' => $this->index_name]);
            }

            // Rinominiamo l'indice temporaneo
            $elasticsearch_client->indices()->putAlias([
                'index' => $this->temp_index,
                'name' => $this->index_name
            ]);

            // Rimuoviamo il flag di reindicizzazione
            Redis::del($this->reindex_key);

            \Log::info('Elasticsearch reindexing completed', [
                'index' => $this->index_name,
                'temp_index' => $this->temp_index
            ]);
        } catch (\Exception $e) {
            \Log::error('Elasticsearch index finalization failed', [
                'index' => $this->index_name,
                'temp_index' => $this->temp_index,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Rimuoviamo il flag di reindicizzazione anche in caso di fallimento
        Redis::del($this->reindex_key);

        \Log::error('FinalizeReindexJob failed', [
            'index' => $this->index_name,
            'temp_index' => $this->temp_index,
            'error' => $exception->getMessage()
        ]);
    }
}
