<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JsonException;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\OllamaConfig;
use LLPhant\OpenAIConfig;
use Psr\Http\Client\ClientExceptionInterface;
use Throwable;

final class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array|int[]  */
    public array $backoff = [30, 60, 120];

    /**
     * Job timeout in seconds
     * 180s (3 min) considering:
     * - 30s per OpenAI call
     * - Multiple calls for long documents
     * - Buffer for network latency and retries.
     */
    public int $timeout = 300;

    /**
     * Maximum time to wait in queue before execution.
     */
    public int $maxExceptionsThenWait = 300;

    public function __construct(
        private readonly object $model,
    ) {
        $this->onQueue('embeddings');
    }

    public function middleware(): array
    {
        return [
            new ThrottlesExceptions(10, 5), // Max 10 exceptions in 5 minutes
            new RateLimited('embeddings'), // Rate limit for the embedding queue
        ];
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function handle(): void
    {
        $data = $this->model->prepareDataToEmbed();

        if ($data === null || $data === '') {
            return;
        }

        try {
            $document = new Document();
            $document->content = $data;
            $splitDocuments = DocumentSplitter::splitDocument($document, 800);
            $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);

            switch (config('search.vector_search.provider')) {
                case 'openai':
                    $config = new OpenAIConfig();
                    $config->apiKey = config('ai.openai_api_key');

                    if (config('ai.openai_api_url')) {
                        $config->url = config('ai.openai_api_url');
                    }

                    if (config('ai.openai_model')) {
                        $config->model = config('ai.openai_model');
                    }
                    $embeddingGenerator = new OpenAI3SmallEmbeddingGenerator($config);
                    $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);

                    break;
                case 'ollama':
                    $config = new OllamaConfig();
                    $config->model = config('ai.ollama_model');

                    if (config('ai.ollama_api_url')) {
                        $config->url = config('ai.ollama_api_url');
                    }
                    $embeddingGenerator = new OllamaEmbeddingGenerator($config);
                    $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);

                    break;
                default:
                    $embeddedDocuments = [];

                    break;
            }

            foreach ($embeddedDocuments as $embeddedDocument) {
                $this->model->embeddings()->create(['embedding' => $embeddedDocument]);
            }
        } catch (Exception $e) {
            Log::error('Embedding generation failed for model: ' . $this->model::class, [
                'model_id' => $this->model->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Rethrow to make the join chain fails
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateEmbeddingsJob failed', [
            'model' => $this->model::class,
            'model_id' => $this->model->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
