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
use Illuminate\Support\Collection;

class BulkIndexElasticsearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    public $timeout = 300; // 5 minuti per processare il batch

    public $maxExceptionsThenWait = 60;

    public $queue = 'indexing';

    public function __construct(
        private readonly Collection $models,
        private readonly string $index_name
    ) {}

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

            $params = ['body' => []];

            foreach ($this->models as $model) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->index_name,
                        '_id' => $model->id
                    ]
                ];

                $params['body'][] = $model->toSearchableArray();

                // Ogni 100 documenti, eseguiamo la bulk request
                if (count($params['body']) >= 200) {
                    $elasticsearch_client->bulk($params);
                    $params = ['body' => []];
                }
            }

            // Inviamo i documenti rimanenti
            if (!empty($params['body'])) {
                $elasticsearch_client->bulk($params);
            }
        } catch (\Exception $e) {
            \Log::error('Elasticsearch bulk indexing failed', [
                'index' => $this->index_name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('BulkIndexElasticsearchJob failed', [
            'index' => $this->index_name,
            'error' => $exception->getMessage()
        ]);
    }
}
