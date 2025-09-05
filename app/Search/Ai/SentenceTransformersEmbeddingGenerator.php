<?php

declare(strict_types=1);

namespace Modules\Core\Search\Ai;

use Exception;
use GuzzleHttp\Client;
use JsonException;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentUtils;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use Psr\Http\Client\ClientExceptionInterface;

final class SentenceTransformersEmbeddingGenerator implements EmbeddingGeneratorInterface
{
    public Client $client;

    public int $batch_size_limit = 128;

    public bool $truncate = true;

    public bool $normalizeEmbeddings = true;

    public function __construct(SentenceTransformersConfig $config)
    {
        $this->truncate = $config->truncate;
        $this->normalizeEmbeddings = $config->normalizeEmbeddings;

        $options = [
            'base_uri' => $config->url,
            'timeout' => $config->timeout,
            'connect_timeout' => $config->timeout,
            'read_timeout' => $config->timeout,
        ];

        if (! empty($config->apiKey)) {
            $options['headers'] = ['Authorization' => 'Bearer ' . $config->apiKey];
        }

        $this->client = new Client($options);
    }

    /**
     * @return float[]
     */
    public function embedText(string $text): array
    {
        $text = str_replace("\n", ' ', DocumentUtils::toUtf8($text));

        $body = [
            'text' => $text,
            'truncation' => $this->truncate,
            'normalize_embeddings' => $this->normalizeEmbeddings,
            'max_length' => $this->getEmbeddingLength(),
        ];

        $response = $this->client->post('embed', [
            'body' => json_encode($body, JSON_THROW_ON_ERROR),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $searchResults = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($searchResults)) {
            throw new Exception("Request to SentenceTransformers didn't returned an array: " . $response->getBody()->getContents());
        }

        if (! isset($searchResults['embeddings'])) {
            throw new Exception("Request to SentenceTransformers didn't returned expected format: " . $response->getBody()->getContents());
        }

        return $searchResults['embeddings'][0];
    }

    public function embedDocument(Document $document): Document
    {
        $text = $document->formattedContent ?? $document->content;
        $document->embedding = $this->embedText($text);

        return $document;
    }

    /**
     * @param  Document[]  $documents
     *
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws Exception
     *
     * @return Document[]
     */
    public function embedDocuments(array $documents): array
    {
        if ($this->batch_size_limit <= 0) {
            throw new Exception('Batch size limit must be greater than 0.');
        }

        // Process documents in batches
        $batches = array_chunk($documents, $this->batch_size_limit);
        $processedDocuments = [];

        foreach ($batches as $batch) {
            $texts = array_map('LLPhant\Embeddings\DocumentUtils::getUtf8Data', $batch);

            $body = [
                'texts' => $texts,
                'truncation' => $this->truncate,
                'normalize_embeddings' => $this->normalizeEmbeddings,
                'max_length' => $this->getEmbeddingLength(),
            ];

            $response = $this->client->post('embed', [
                'body' => json_encode($body, JSON_THROW_ON_ERROR),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $searchResults = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($searchResults)) {
                throw new Exception("Request to SentenceTransformers didn't returned an array: " . $response->getBody()->getContents());
            }

            if (! isset($searchResults['embeddings'])) {
                throw new Exception("Request to SentenceTransformers didn't returned expected format: " . $response->getBody()->getContents());
            }

            // Assign embeddings to documents in this batch
            for ($i = 0; $i < count($batch); $i++) {
                $batch[$i]->embedding = $searchResults['embeddings'][$i];
                $processedDocuments[] = $batch[$i];
            }
        }

        return $processedDocuments;
    }

    public function getEmbeddingLength(): int
    {
        return 512;
    }
}
