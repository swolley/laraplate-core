<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
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
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Embeddings\EmbeddingGenerator\Mistral\MistralEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\Ollama\OllamaEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3LargeEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAIADA002EmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage3EmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage3LargeEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\Voyage3LiteEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\VoyageCode2EmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\VoyageCode3EmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\VoyageFinance2EmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\VoyageAI\VoyageLaw2EmbeddingGenerator;
use LLPhant\OllamaConfig;
use LLPhant\OpenAIConfig;
use LLPhant\VoyageAIConfig;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use Throwable;

final class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array|int[]
     */
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
     * Maximum time to wait in the queue before execution.
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
            $embeddedDocuments = self::embedDocument($data);

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

    private static function getGenerator(): ?EmbeddingGeneratorInterface
    {
        switch (config('search.vector_search.provider')) {
            case 'openai':
                $config = new OpenAIConfig();
                $config->apiKey = config('ai.openai_api_key');

                return match (config('ai.openai_model')) {
                    'text-embedding-3-large' => new OpenAI3LargeEmbeddingGenerator($config),
                    'text-embedding-ada-002' => new OpenAIADA002EmbeddingGenerator($config),
                    default => new OpenAI3SmallEmbeddingGenerator($config),
                };
            case 'ollama':
                $config = new OllamaConfig();
                $config->model = match (config('ai.ollama_model')) {
                    'nomic-embed-large' => 'nomic-embed-large',
                    default => 'nomic-embed-text',
                };

                $config->url = config('ai.ollama_api_url', 'http://localhost:11434/api/embeddings');

                return new OllamaEmbeddingGenerator($config);
            case 'voyageai':
                $config = new VoyageAIConfig();
                $config->apiKey = config('ai.voyageai_api_key');

                return match (config('ai.voyageai_model')) {
                    'voyage-3' => new Voyage3EmbeddingGenerator($config),
                    'voyage-3-large' => new Voyage3LargeEmbeddingGenerator($config),
                    'voyage-code-2' => new VoyageCode2EmbeddingGenerator($config),
                    'voyage-code-3' => new VoyageCode3EmbeddingGenerator($config),
                    'voyage-finance-2' => new VoyageFinance2EmbeddingGenerator($config),
                    'voyage-law-2' => new VoyageLaw2EmbeddingGenerator($config),
                    default => new Voyage3LiteEmbeddingGenerator($config),
                };
            case 'mistral':
                $config = new OpenAIConfig();
                $config->apiKey = config('ai.mistral_api_key');

                return new MistralEmbeddingGenerator($config);
            default:
                return null;
        }
    }

    /**
     * 
     * @param string $data 
     * 
     * @return Document[] 
     * 
     * @throws BindingResolutionException 
     * @throws ClientExceptionInterface 
     * @throws JsonException 
     * @throws Exception 
     * @throws GuzzleException 
     * @throws RuntimeException 
     */
    public static function embedDocument(string $data): array
    {
        $document = new Document();
        $document->content = $data;
        $splitDocuments = DocumentSplitter::splitDocument($document);
        $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splitDocuments);

        $generator = self::getGenerator();
        if ($generator === null) {
            return [];
        }

        return $generator->embedDocuments($formattedDocuments);
    }

    /**
     * 
     * @param string $text 
     * 
     * @return float[] 
     * 
     * @throws BindingResolutionException 
     */
    public static function embedText(string $text): array
    {
        $generator = self::getGenerator();
        if ($generator === null) {
            return [];
        }
        return $generator->embedText($text);
    }
}
