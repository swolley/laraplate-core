<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Support\Facades\Log;
use Modules\Core\Search\Exceptions\ElasticsearchException;

final class ElasticsearchService
{
    /**
     * Elasticsearch client instance.
     */
    public Client $client {
        get {
            return $this->client;
        }
    }

    /**
     * Singleton instance of the service.
     */
    private static ?self $instance = null;

    /**
     * Create a new elasticsearch service instance.
     */
    private function __construct()
    {
        $this->client = $this->createClient();
    }

    /**
     * Get service instance (singleton pattern).
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Create or update index.
     *
     * @param  string  $index  Index name
     * @param  array<string,mixed>  $settings  Index settings
     * @param  array<string,mixed>  $mappings  Index mappings
     *
     * @throws ElasticsearchException|MissingParameterException
     */
    public function createIndex(string $index, array $settings = [], array $mappings = []): bool
    {
        try {
            // Check if the index already exists
            $exists = $this->client->indices()->exists(['index' => $index])->asBool();

            if ($exists) {
                // Update mappings and settings of existing index
                if ($mappings !== []) {
                    $this->client->indices()->putMapping([
                        'index' => $index,
                        'body' => $mappings,
                    ]);
                }

                if ($settings !== []) {
                    $this->client->indices()->putSettings([
                        'index' => $index,
                        'body' => ['settings' => $settings],
                    ]);
                }

                return true;
            }

            // Create a new index
            $params = ['index' => $index, 'body' => []];

            if ($settings !== []) {
                $params['body']['settings'] = $settings;
            }

            if ($mappings !== []) {
                $params['body']['mappings'] = $mappings;
            }

            $response = $this->client->indices()->create($params);

            return $response->asBool();
        } catch (ClientResponseException|ServerResponseException $e) {
            Log::error('Elasticsearch create index error', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error creating index: ' . $e->getMessage());
        }
    }

    /**
     * Delete index if exists.
     *
     * @param  string  $index  Index name
     *
     * @throws ElasticsearchException|MissingParameterException
     */
    public function deleteIndex(string $index): bool
    {
        try {
            // Check if the index exists
            $exists = $this->client->indices()->exists(['index' => $index])->asBool();

            if (! $exists) {
                return true;
            }

            // Delete the index
            $response = $this->client->indices()->delete(['index' => $index]);

            return $response->asBool();
        } catch (ClientResponseException|ServerResponseException $e) {
            Log::error('Elasticsearch delete index error', [
                'index' => $index,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error deleting index: ' . $e->getMessage());
        }
    }

    /**
     * Bulk index documents.
     *
     * @param  string  $index  Index name
     * @param  array<string|int,array<string,mixed>>  $documents  Documents to index
     *
     * @throws ElasticsearchException
     *
     * @return array Response with success/error counts
     */
    public function bulkIndex(string $index, array $documents): array
    {
        if ($documents === []) {
            return ['indexed' => 0, 'failed' => 0, 'errors' => []];
        }

        $params = ['body' => []];
        $errors = [];

        // Prepare documents for bulk indexing
        foreach ($documents as $id => $document) {
            $params['body'][] = [
                'index' => [
                    '_index' => $index,
                    '_id' => $id,
                ],
            ];

            $params['body'][] = $document;
        }

        try {
            $response = $this->client->bulk($params);
            $result = $response->asArray();

            // Analyze results
            $indexed = 0;
            $failed = 0;

            if (isset($result['items'])) {
                foreach ($result['items'] as $item) {
                    if (isset($item['index']['status']) && $item['index']['status'] >= 200 && $item['index']['status'] < 300) {
                        $indexed++;
                    } else {
                        $failed++;
                        $errors[] = $item['index']['error'] ?? 'Unknown error';
                    }
                }
            }

            return [
                'indexed' => $indexed,
                'failed' => $failed,
                'errors' => $errors,
            ];
        } catch (ClientResponseException|ServerResponseException $e) {
            Log::error('Elasticsearch bulk index error', [
                'index' => $index,
                'documents_count' => count($documents),
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error in bulk indexing: ' . $e->getMessage());
        }
    }

    /**
     * Search documents.
     *
     * @param  string  $index  Index name
     * @param  array<string,mixed>  $query  Elasticsearch query
     *
     * @throws ElasticsearchException
     *
     * @return array Search results
     */
    public function search(string $index, array $query): array
    {
        try {
            $params = [
                'index' => $index,
                'body' => $query,
            ];

            $response = $this->client->search($params);

            return $response->asArray();
        } catch (ClientResponseException|ServerResponseException $e) {
            Log::error('Elasticsearch search error', [
                'index' => $index,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Search error: ' . $e->getMessage());
        }
    }

    /**
     * Get document by ID.
     *
     * @param  string  $index  Index name
     * @param  string  $id  Document ID
     *
     * @throws ElasticsearchException
     *
     * @return array<string,mixed>|null Document data or null if not found
     */
    public function getDocument(string $index, string $id): ?array
    {
        try {
            $params = [
                'index' => $index,
                'id' => $id,
            ];

            $response = $this->client->get($params);

            return $response->asArray();
        } catch (ClientResponseException $e) {
            // 404 is normal case, return null
            if ($e->getCode() === 404) {
                return null;
            }

            Log::error('Elasticsearch get document error', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error retrieving document: ' . $e->getMessage());
        } catch (ServerResponseException $e) {
            Log::error('Elasticsearch get document error', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error retrieving document: ' . $e->getMessage());
        }
    }

    /**
     * Delete document by ID.
     *
     * @param  string  $index  Index name
     * @param  string|int  $id  Document ID
     * @param  bool  $refresh  Whether to refresh the index immediately
     *
     * @throws ElasticsearchException
     *
     * @return bool Success or failure
     */
    public function deleteDocument(string $index, string|int $id, bool $refresh = false): bool
    {
        try {
            // Check if the document exists
            $exists = $this->client->exists([
                'index' => $index,
                'id' => $id,
            ])->asBool();

            if (! $exists) {
                return true;
            }

            // Delete the document
            $params = [
                'index' => $index,
                'id' => $id,
            ];

            if ($refresh) {
                $params['refresh'] = 'true';
            }

            $response = $this->client->delete($params);

            return $response->asBool();
        } catch (ClientResponseException $e) {
            // 404 is not an error in this context
            if ($e->getCode() === 404) {
                return false;
            }

            Log::error('Elasticsearch delete document error', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error deleting document: ' . $e->getMessage());
        } catch (ServerResponseException $e) {
            Log::error('Elasticsearch delete document error', [
                'index' => $index,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new ElasticsearchException('Error deleting document: ' . $e->getMessage());
        }
    }

    /**
     * Create elasticsearch client.
     */
    private function createClient(): Client
    {
        $config = config('elastic.client.connections.' . config('elastic.client.default', 'default'));

        $builder = ClientBuilder::create();
        $builder->setHosts($config['hosts']);

        // Set authentication if configured
        if (isset($config['username']) && isset($config['password'])) {
            $builder->setBasicAuthentication($config['username'], $config['password']);
        }

        // Set retry configuration
        if ($config['retries'] !== null && $config['retries'] !== 0) {
            $builder->setRetries($config['retries']);
        }

        // Set timeout options
        $builder->setHttpClientOptions([
            'timeout' => $config['timeout'] ?? 60,
            'connect_timeout' => $config['connect_timeout'] ?? 10,
        ]);

        // Set cloud ID if available
        if (isset($config['cloud_id']) && $config['cloud_id'] !== '') {
            $builder->setElasticCloudId($config['cloud_id']);
        }

        // Set SSL configuration
        if (isset($config['ssl_verification'])) {
            $builder->setSSLVerification($config['ssl_verification']);
        }

        return $builder->build();
    }
}
