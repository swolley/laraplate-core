<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\ScoutDriverPlus\Engine as BaseEngine;
use Http\Promise\Promise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\BulkIndexSearchJob;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;
use Modules\Core\Search\Jobs\IndexInSearchJob;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\Services\ElasticsearchService;
use Override;
use stdClass;

/**
 * Implementation of the search engine for Elasticsearch.
 */
class ElasticsearchEngine extends BaseEngine implements ISearchEngine
{
    public array $config;

    public function supportsVectorSearch(): bool
    {
        return true;
    }

    public function index(Model|Collection $model): void
    {
        if ($model instanceof Collection) {
            if ($model->isEmpty()) {
                return;
            }
            $this->ensureSearchable($model->first());
        } else {
            $this->ensureSearchable($model);
        }

        IndexInSearchJob::dispatch($model);
    }

    public function indexWithEmbedding(Model|Collection $model): void
    {
        $this->ensureSearchable($model);

        Bus::chain([
            new GenerateEmbeddingsJob($model),
            new IndexInSearchJob($model),
        ])->dispatch();
    }

    public function bulkIndex(iterable $models): void
    {
        if (count($models) === 0) {
            return;
        }

        $firstModel = $models[0] ?? $models->first();
        $this->ensureSearchable($firstModel);

        BulkIndexSearchJob::dispatch(collect($models), $firstModel->searchableAs());
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function vectorSearch(array $vector, array $options = []): array
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $index = $options['index'] ?? null;

        $params = [
            'index' => $index,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [
                            [
                                'script_score' => [
                                    'query' => ['match_all' => new stdClass()],
                                    'script' => [
                                        'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
                                        'params' => ['query_vector' => $vector],
                                    ],
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                    ],
                ],
            ],
        ];

        // Configure result size
        if (isset($options['size'])) {
            $params['size'] = $options['size'];
        }

        // Configure pagination
        if (isset($options['from'])) {
            $params['from'] = $options['from'];
        }

        // Filters
        if (isset($options['filters']) && $options['filters'] !== []) {
            $filters = $this->buildSearchFilters($options['filters']);

            if ($filters !== []) {
                $params['body']['query']['bool']['must'] = array_merge(
                    $params['body']['query']['bool']['must'],
                    $filters,
                );
            }
        }

        // Sorting
        $params['body']['sort'] = $options['sort'] ?? ['_score' => ['order' => 'desc']];

        // Specific fields only
        if (isset($options['_source'])) {
            $params['body']['_source'] = $options['_source'];
        }

        // Execute query
        $results = $client->search($params);

        return $results->asArray();
    }

    public function buildSearchFilters(array $filters): array
    {
        $esFilters = [];

        foreach ($filters as $field => $value) {
            if (is_array($value) && isset($value['type'])) {
                // Advanced filters with explicit type
                switch ($value['type']) {
                    case 'term':
                        $esFilters[] = ['term' => [$field => $value['value']]];

                        break;
                    case 'terms':
                        $esFilters[] = ['terms' => [$field => $value['value']]];

                        break;
                    case 'range':
                        $esFilters[] = ['range' => [$field => $value['value']]];

                        break;
                    case 'match':
                        $esFilters[] = ['match' => [$field => $value['value']]];

                        break;
                    case 'wildcard':
                        $esFilters[] = ['wildcard' => [$field => $value['value']]];

                        break;
                    case 'exists':
                        $esFilters[] = ['exists' => ['field' => $field]];

                        break;
                    case 'geo_distance':
                        $esFilters[] = [
                            'geo_distance' => [
                                'distance' => $value['distance'],
                                $field => $value['point'],
                            ],
                        ];

                        break;
                }
            } elseif (is_array($value) && count($value) === 2 && isset($value[0]) && isset($value[1])) {
                // Assume it's a range
                $esFilters[] = [
                    'range' => [
                        $field => [
                            'gte' => $value[0],
                            'lte' => $value[1],
                        ],
                    ],
                ];
            } else {
                // Simple exact match filter
                $esFilters[] = ['term' => [$field => $value]];
            }
        }

        return $esFilters;
    }

    public function reindex(string $modelClass): void
    {
        ReindexSearchJob::dispatch($modelClass);
    }

    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        if (! class_exists($modelClass)) {
            throw new InvalidArgumentException("Class {$modelClass} does not exist");
        }

        if (! $this->usesSearchableTrait(new $modelClass())) {
            throw new InvalidArgumentException("Model {$modelClass} does not implement the Searchable trait");
        }

        $query = $modelClass::query();

        // Support for soft delete
        if (method_exists($modelClass, 'withTrashed')) {
            $query->withTrashed();
        }

        // Filters
        if ($id !== null && $id !== 0) {
            $query->where('id', $id);
        } elseif ($from !== null && $from !== '' && $from !== '0') {
            $query->where('updated_at', '>', Carbon::parse($from));
        } else {
            $lastIndexed = new $modelClass()->getLastIndexedTimestamp();

            if ($lastIndexed) {
                $query->where('updated_at', '>', $lastIndexed);
            }
        }

        $count = $query->count();

        // If no records, do nothing
        if ($count === 0) {
            return 0;
        }

        // Sync each record
        $query->chunk(100, function ($records): void {
            foreach ($records as $record) {
                $this->indexDocument($record);
            }
        });

        return $count;
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $index = $this->getIndexName($model);

        $query = [
            'index' => $index,
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]],
                        ],
                    ],
                ],
                'aggs' => [
                    'over_time' => [
                        'date_histogram' => [
                            'field' => $filters['date_field'] ?? 'valid_from',
                            'calendar_interval' => $interval,
                        ],
                    ],
                ],
            ],
        ];

        // Add filters if present
        $response = $this->setFilters($filters, $query, $client);

        return $response['aggregations']['over_time']['buckets'] ?? [];
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $index = $this->getIndexName($model);

        $query = [
            'index' => $index,
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]],
                        ],
                    ],
                ],
                'aggs' => [
                    'by_term' => [
                        'terms' => [
                            'field' => $field,
                            'size' => $size,
                        ],
                    ],
                ],
            ],
        ];

        // Add filters if present
        $response = $this->setFilters($filters, $query, $client);

        return $response['aggregations']['by_term']['buckets'] ?? [];
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $index = $this->getIndexName($model);

        $query = [
            'index' => $index,
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]],
                        ],
                    ],
                ],
                'aggs' => [
                    'geo_clusters' => [
                        'geohash_grid' => [
                            'field' => $geoField,
                            'precision' => 5,
                        ],
                    ],
                ],
            ],
        ];

        // Add filters if present
        $response = $this->setFilters($filters, $query, $client);

        return $response['aggregations']['geo_clusters']['buckets'] ?? [];
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $index = $this->getIndexName($model);

        $query = [
            'index' => $index,
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]],
                        ],
                    ],
                ],
                'aggs' => [
                    'field_stats' => [
                        'stats' => [
                            'field' => $field,
                        ],
                    ],
                ],
            ],
        ];

        // Add filters if present
        $response = $this->setFilters($filters, $query, $client);

        return $response['aggregations']['field_stats'] ?? [];
    }

    /**
     * @throws ServerResponseException
     * @throws ClientResponseException
     */
    public function getHistogram(Model $model, string $field, array $filters = [], $interval = 50): array
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $index = $this->getIndexName($model);

        $query = [
            'index' => $index,
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]],
                        ],
                    ],
                ],
                'aggs' => [
                    'histogram' => [
                        'histogram' => [
                            'field' => $field,
                            'interval' => $interval,
                        ],
                    ],
                ],
            ],
        ];

        // Add filters if present
        $response = $this->setFilters($filters, $query, $client);

        return $response['aggregations']['histogram']['buckets'] ?? [];
    }

    public function deleteDocument(Model $model): void
    {
        $this->delete(collect([$model]));
    }

    public function checkIndex(Model $model): bool
    {
        return $this->checkIndexExists($model);
    }

    public function getSearchMapping(Model $model): array
    {
        return $model->getSearchMapping() ?? [];
    }

    public function prepareDataToEmbed(Model $model): array
    {
        return $model->toSearchableArray();
    }

    public function getLastIndexedTimestamp(Model $model): ?string
    {
        return $model->getLastIndexedTimestamp();
    }

    protected function checkIndexExists(Model $model): bool
    {
        $client = ElasticsearchService::getInstance()->getClient();
        $indexName = $this->getIndexName($model);

        return $client->indices()->exists(['index' => $indexName])->asBool();
    }

    /**
     * Get the index name for the model.
     */
    protected function getIndexName(Model $model): string
    {
        $indexName = $model->searchableAs();

        // Add prefix if configured
        if ($this->config['index_prefix'] !== '' && $this->config['index_prefix'] !== null) {
            return $this->config['index_prefix'] . $indexName;
        }

        return $indexName;
    }

    /**
     * Ensure the model is searchable and index exists.
     */
    protected function ensureSearchable(Model $model): void
    {
        if (! $this->usesSearchableTrait($model)) {
            throw new InvalidArgumentException('Model ' . get_class($model) . ' does not implement the Searchable trait');
        }

        if (! $this->checkIndexExists($model)) {
            $this->createIndex($model);
        }
    }

    /**
     * Check if model uses the Searchable trait.
     */
    protected function usesSearchableTrait(Model $model): bool
    {
        return in_array(Searchable::class, class_uses_recursive($model), true);
    }

    /**
     * @param array|null $filters
     * @param array $query
     * @param Client $client
     *
     * @return Elasticsearch|Promise
     *
     * @throws ClientResponseException
     * @throws ServerResponseException
     */
    public function setFilters(?array $filters, array $query, Client $client): Promise|Elasticsearch
    {
        if ($filters !== [] && $filters !== null) {
            $esFilters = $this->buildSearchFilters($filters);

            if ($esFilters !== []) {
                $query['body']['query']['bool']['must'] = array_merge(
                    $query['body']['query']['bool']['must'],
                    $esFilters,
                );
            }
        }

        return $client->search($query);
    }

    #[Override]
    public function ensureIndex(Model $model): bool
    {
        // TODO: Implement ensureIndex() method.
    }
}
