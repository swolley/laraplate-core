<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Elastic\ScoutDriverPlus\Engine as BaseElasticsearchEngine;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\CommonEngineFunctions;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\Services\ElasticsearchService;
use Override;
use stdClass;

/**
 * Implementation of the search engine for Elasticsearch.
 */
final class ElasticsearchEngine extends BaseElasticsearchEngine implements ISearchEngine
{
    use CommonEngineFunctions;

    //    public array $config;

    #[Override]
    public function supportsVectorSearch(): bool
    {
        return true;
    }

    /**
     * @throws Exception
     * @throws \Http\Client\Exception
     */
    #[Override]
    public function createIndex($name, array $options = [], bool $force = false): void
    {
        try {
            $matched = $this->matchModelToCollectionName($name);

            if ($matched === null) {
                throw new Exception('Unable to resolve collection name for index creation.');
            }

            $model = $matched['model'];
            $collection = $matched['collection'];

            if (! $force && $this->checkIndex($model)) {
                return;
            }

            // Get mapping from the model
            $schema = [];

            if (method_exists($model, 'getSearchMapping')) {
                $schema = $model->getSearchMapping();
            } elseif (method_exists($model, 'toSearchableIndex')) {
                $schema = $model->toSearchableIndex();
            } else {
                throw new Exception('No schema definition method found on model ' . $model::class);
            }

            if ($force) {
                // Drop existing collection to ensure schema is up to date
                try {
                    $this->deleteIndex($collection);
                } catch (Exception) {
                    // Ignore if it does not exist
                }
            }

            // Add collection name to schema
            $schema['name'] = $collection;

            parent::createIndex($collection, $schema);
            Log::info(sprintf("Elasticsearch collection '%s' created", $collection));
        } catch (Exception $exception) {
            Log::error(sprintf("Error creating Elasticsearch collection '%s'", $collection), [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    #[Override]
    public function search(Builder $builder)
    {
        // Check if it's a vector search.
        if ($this->isVectorSearch($builder)) {
            return $this->performVectorSearch($builder);
        }

        // Otherwise, use the traditional search.
        return parent::search($builder);
    }

    #[Override]
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

    #[Override]
    public function reindex(string $modelClass): void
    {
        dispatch(new ReindexSearchJob($modelClass));
    }

    #[Override]
    /**
     * @param  class-string<Model>  $modelClass
     *
     * @throws \Http\Client\Exception
     * @throws JsonException
     */
    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        throw_unless(class_exists($modelClass), InvalidArgumentException::class, sprintf('Class %s does not exist', $modelClass));

        throw_unless($this->usesSearchableTrait(new $modelClass()), InvalidArgumentException::class, sprintf('Model %s does not implement the Searchable trait', $modelClass));

        $query = $modelClass::query();

        // Support for soft delete
        if (method_exists($modelClass, 'withTrashed')) {
            $query->withTrashed();
        }

        // Filters
        if ($id !== null && $id !== 0) {
            $query->where('id', $id);
        } elseif (! in_array($from, [null, '', '0'], true)) {
            $query->where('updated_at', '>', Date::parse($from));
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
        $query->chunk(100, function (Collection $records): void {
            $this->update($records);
        });

        return $count;
    }

    //    /**
    //     * @throws ServerResponseException
    //     * @throws ClientResponseException
    //     */
    //    public function vectorSearch(array $vector, array $options = []): array
    //    {
    //        $client = ElasticsearchService::getInstance()->client;
    //        $index = $options['index'] ?? null;
    //
    //        $params = [
    //            'index' => $index,
    //            'body' => [
    //                'query' => [
    //                    'bool' => [
    //                        'must' => [],
    //                        'should' => [
    //                            [
    //                                'script_score' => [
    //                                    'query' => ['match_all' => new stdClass()],
    //                                    'script' => [
    //                                        'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
    //                                        'params' => ['query_vector' => $vector],
    //                                    ],
    //                                ],
    //                            ],
    //                        ],
    //                        'minimum_should_match' => 1,
    //                    ],
    //                ],
    //            ],
    //        ];
    //
    //        // Configure result size
    //        if (isset($options['size'])) {
    //            $params['size'] = $options['size'];
    //        }
    //
    //        // Configure pagination
    //        if (isset($options['from'])) {
    //            $params['from'] = $options['from'];
    //        }
    //
    //        // Filters
    //        if (isset($options['filters']) && $options['filters'] !== []) {
    //            $filters = $this->buildSearchFilters($options['filters']);
    //
    //            if ($filters !== []) {
    //                $params['body']['query']['bool']['must'] = array_merge(
    //                    $params['body']['query']['bool']['must'],
    //                    $filters,
    //                );
    //            }
    //        }
    //
    //        // Sorting
    //        $params['body']['sort'] = $options['sort'] ?? ['_score' => ['order' => 'desc']];
    //
    //        // Specific fields only
    //        if (isset($options['_source'])) {
    //            $params['body']['_source'] = $options['_source'];
    //        }
    //
    //        // Execute query
    //        $results = $client->search($params);
    //
    //        return $results->asArray();
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws ServerResponseException
    //     * @throws ClientResponseException
    //     */
    //    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array
    //    {
    //        //        $client = ElasticsearchService::getInstance()->getClient();
    //        //        $index = $model->searchableAs();
    //
    //        $response = $model::searchQuery()
    //            ->bool()
    //            ->must('match', ['entity' => $model->getTable()])
    //            ->aggregation('over_time', [
    //                'date_histogram' => [
    //                    'field' => $filters['date_field'] ?? 'valid_from',
    //                    'calendar_interval' => $interval,
    //                ],
    //            ])
    //            ->size(0)
    //            ->execute();
    //
    //        //        $query = [
    //        //            'index' => $index,
    //        //            'body' => [
    //        //                'size' => 0,
    //        //                'query' => [
    //        //                    'bool' => [
    //        //                        'must' => [
    //        //                            ['match' => ['entity' => $model->getTable()]],
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //                'aggs' => [
    //        //                    'over_time' => [
    //        //                        'date_histogram' => [
    //        //                            'field' => $filters['date_field'] ?? 'valid_from',
    //        //                            'calendar_interval' => $interval,
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //            ],
    //        //        ];
    //
    //        // Add filters if present
    //        //        $response = $this->setFilters($filters, $query, $client);
    //
    //        return $response['aggregations']['over_time']['buckets'] ?? [];
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws ServerResponseException
    //     * @throws ClientResponseException
    //     */
    //    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array
    //    {
    //        //        $client = ElasticsearchService::getInstance()->getClient();
    //        //        $index = $this->getIndexName($model);
    //
    //        $response = $model::searchQuery()
    //            ->bool()
    //            ->must('match', ['entity' => $model->getTable()])
    //            ->aggregation('by_term', [
    //                'terms' => [
    //                    'field' => $field,
    //                    'size' => $size,
    //                ],
    //            ])
    //            ->size(0)
    //            ->execute();
    //
    //        //        $query = [
    //        //            'index' => $index,
    //        //            'body' => [
    //        //                'size' => 0,
    //        //                'query' => [
    //        //                    'bool' => [
    //        //                        'must' => [
    //        //                            ['match' => ['entity' => $model->getTable()]],
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //                'aggs' => [
    //        //                    'by_term' => [
    //        //                        'terms' => [
    //        //                            'field' => $field,
    //        //                            'size' => $size,
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //            ],
    //        //        ];
    //
    //        // Add filters if present
    //        //        $response = $this->setFilters($filters, $query, $client);
    //
    //        return $response['aggregations']['by_term']['buckets'] ?? [];
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws ServerResponseException
    //     * @throws ClientResponseException
    //     */
    //    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array
    //    {
    //        //        $client = ElasticsearchService::getInstance()->getClient();
    //        //        $index = $this->getIndexName($model);
    //
    //        $response = $model::searchQuery()
    //            ->bool()
    //            ->must('match', ['entity' => $model->getTable()])
    //            ->aggregation('geo_clusters', [
    //                'geohash_grid' => [
    //                    'field' => $geoField,
    //                    'precision' => 5,
    //                ],
    //            ])
    //            ->size(0)
    //            ->execute();
    //
    //        //        $query = [
    //        //            'index' => $index,
    //        //            'body' => [
    //        //                'size' => 0,
    //        //                'query' => [
    //        //                    'bool' => [
    //        //                        'must' => [
    //        //                            ['match' => ['entity' => $model->getTable()]],
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //                'aggs' => [
    //        //                    'geo_clusters' => [
    //        //                        'geohash_grid' => [
    //        //                            'field' => $geoField,
    //        //                            'precision' => 5,
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //            ],
    //        //        ];
    //        //
    //        //        // Add filters if present
    //        //        $response = $this->setFilters($filters, $query, $client);
    //
    //        return $response['aggregations']['geo_clusters']['buckets'] ?? [];
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws ServerResponseException
    //     * @throws ClientResponseException
    //     */
    //    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array
    //    {
    //        //        $client = ElasticsearchService::getInstance()->getClient();
    //        //        $index = $this->getIndexName($model);
    //
    //        $response = $model::searchQuery()
    //            ->bool()
    //            ->must('match', ['entity' => $model->getTable()])
    //            ->aggregation('field_stats', [
    //                'stats' => [
    //                    'field' => $field,
    //                ],
    //            ])
    //            ->size(0)
    //            ->execute();
    //
    //        //        $query = [
    //        //            'index' => $index,
    //        //            'body' => [
    //        //                'size' => 0,
    //        //                'query' => [
    //        //                    'bool' => [
    //        //                        'must' => [
    //        //                            ['match' => ['entity' => $model->getTable()]],
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //                'aggs' => [
    //        //                    'field_stats' => [
    //        //                        'stats' => [
    //        //                            'field' => $field,
    //        //                        ],
    //        //                    ],
    //        //                ],
    //        //            ],
    //        //        ];
    //        //
    //        //        // Add filters if present
    //        //        $response = $this->setFilters($filters, $query, $client);
    //
    //        return $response['aggregations']['field_stats'] ?? [];
    //    }

    //    /**
    //     * @throws ServerResponseException
    //     * @throws ClientResponseException
    //     */
    //    public function getHistogram(Model $model, string $field, array $filters = [], $interval = 50): array
    //    {
    //        $client = $ElasticsearchService::getInstance()->client;
    //        $index = $this->getIndexName($model);
    //
    //        $query = [
    //            'index' => $index,
    //            'body' => [
    //                'size' => 0,
    //                'query' => [
    //                    'bool' => [
    //                        'must' => [
    //                            ['match' => ['entity' => $model->getTable()]],
    //                        ],
    //                    ],
    //                ],
    //                'aggs' => [
    //                    'histogram' => [
    //                        'histogram' => [
    //                            'field' => $field,
    //                            'interval' => $interval,
    //                        ],
    //                    ],
    //                ],
    //            ],
    //        ];
    //
    //        // Add filters if present
    //        $response = $this->setFilters($filters, $query, $client);
    //
    //        return $response['aggregations']['histogram']['buckets'] ?? [];
    //    }

    #[Override]
    /**
     * @param  Model&Searchable  $model
     */
    public function getSearchMapping(Model $model): array
    {
        if (method_exists($model, 'getSearchMapping')) {
            return $model->getSearchMapping();
        }

        // Default mapping for Elasticsearch
        $mapping = [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'entity' => ['type' => 'keyword'],
                    'connection' => ['type' => 'keyword'],
                    self::INDEXED_AT_FIELD => ['type' => 'date'],
                ],
            ],
        ];

        // Add a vector field if needed
        if (config('search.vector_search.enabled') && $this->supportsVectorSearch()) {
            $dimension = config('search.vector_search.dimension', 1536);
            $similarity = config('search.vector_search.similarity', 'cosine');

            $mapping['mappings']['properties']['embedding'] = [
                'type' => 'dense_vector',
                'dims' => $dimension,
                'index' => true,
                'similarity' => $similarity,
            ];
        }

        return $mapping;
    }

    /**
     * @param  string|Model|class-string<Model>  $model
     */
    #[Override]
    public function checkIndex(string|Model $model): bool
    {
        try {
            if ($model instanceof Model) {
                $this->ensureSearchable($model);
                $collection = $model->searchableAs();
            } elseif (is_string($model) && class_exists($model)) {
                $collection = new $model()->searchableAs();
            } else {
                $collection = $model;
            }

            return $this->indexManager->exists($collection);
        } catch (Exception) {
            return false;
        }
    }

    //    /**
    //     * @throws ClientResponseException
    //     * @throws ServerResponseException
    //     */
    //    public function setFilters(?array $filters, array $query, Client $client): Promise|Elasticsearch
    //    {
    //        if ($filters !== [] && $filters !== null) {
    //            $esFilters = $this->buildSearchFilters($filters);
    //
    //            if ($esFilters !== []) {
    //                $query['body']['query']['bool']['must'] = array_merge(
    //                    $query['body']['query']['bool']['must'],
    //                    $esFilters,
    //                );
    //            }
    //        }
    //
    //        return $client->search($query);
    //    }

    //    #[Override]
    //    public function ensureIndexExists(Model $model): bool
    //    {
    //        // Integrazione con Scout per verificare l'esistenza dell'indice
    //        if (method_exists(parent::class, 'indexExists')) {
    //            if (!parent::indexExists($model->searchableAs())) {
    //                // Utilizzare il metodo nativo di Scout se disponibile
    //                if (method_exists(parent::class, 'createIndex')) {
    //                    parent::createIndex($model->searchableAs(), $this->getSearchMapping($model));
    //                } else {
    //                    $this->createIndex($model);
    //                }
    //                return true;
    //            }
    //            return false;
    //        }
    //
    //        // Fallback al comportamento esistente
    //        if (!$this->checkIndex($model)) {
    //            $this->createIndex($model);
    //            return true;
    //        }
    //        return false;
    //    }

    #[Override]
    public function health(): array
    {
        $health = ElasticsearchService::getInstance()->client->cluster()->health();
        // TODO: stats or state('metrics') ???
        $metrics = ElasticsearchService::getInstance()->client->cluster()->stats();

        return [
            'status' => $health->asArray()['status'] ?? 'danger',
            'metrics' => $metrics->asArray(),
        ];
    }

    #[Override]
    public function stats(): array
    {
        $health = ElasticsearchService::getInstance()->client->cluster()->health();

        return $health->asArray();
    }

    private function performVectorSearch(Builder $builder): mixed
    {
        // Extract the vector from the builder
        $vector = $this->extractVectorFromBuilder($builder);

        if ($vector === []) {
            // Fallback to regular search if no vector provided
            return parent::search($builder);
        }

        /** @var Model&Searchable $model */
        $model = $builder->model;
        $index = $model->searchableAs();

        // Build the vector search query using knn (more efficient than script_score)
        $query = [
            'knn' => [
                'field' => 'embedding',
                'query_vector' => $vector,
                'k' => $builder->limit ?: 10,
                'num_candidates' => min(($builder->limit ?: 10) * 10, 100),
            ],
        ];

        // Add filters if any are present
        if (! empty($builder->wheres)) {
            $filters = $this->buildSearchFilters($builder->wheres);

            if ($filters !== []) {
                $query['knn']['filter'] = [
                    'bool' => [
                        'must' => $filters,
                    ],
                ];
            }
        }

        // Combine with text search if query is provided
        $body = [
            'knn' => $query['knn'],
        ];

        if ($builder->query && $builder->query !== '*') {
            $body['query'] = [
                'bool' => [
                    'should' => [
                        [
                            'multi_match' => [
                                'query' => $builder->query,
                                'fields' => ['*'],
                                'type' => 'best_fields',
                            ],
                        ],
                    ],
                ],
            ];
        }

        // Execute the search query using ElasticsearchService
        $client = ElasticsearchService::getInstance()->client;

        $params = [
            'index' => $index,
            'body' => $body,
            'size' => $builder->limit ?: 10,
        ];

        $response = $client->search($params);

        // Transform results to match Scout's expected format
        $results = $response->asArray();
        $hits = $results['hits']['hits'] ?? [];

        return collect($hits)->map(function (array $hit) {
            $document = $hit['_source'] ?? [];
            $document['_id'] = $hit['_id'] ?? null;
            $document['_score'] = $hit['_score'] ?? 0;

            return $document;
        });
    }
}
