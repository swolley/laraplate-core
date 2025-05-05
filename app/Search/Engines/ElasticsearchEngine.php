<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Illuminate\Support\Carbon;
use Elastic\ScoutDriverPlus\Engine;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Jobs\IndexInSearchJob;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Jobs\BulkIndexSearchJob;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;
use Modules\Core\Search\Contracts\SearchEngineInterface;
use Modules\Core\Search\Contracts\SearchAnalyticsInterface;

/**
 * Implementazione del motore di ricerca per Elasticsearch
 */
class ElasticsearchEngine extends Engine implements SearchEngineInterface, SearchAnalyticsInterface
{
    public function supportsVectorSearch(): bool
    {
        return true;
    }

    // /**
    //  * {@inheritdoc}
    //  */
    // public function indexDocument(Model $model): void
    // {
    //     $this->ensureSearchable($model);
    //     IndexInSearchJob::dispatch($model);
    // }

    public function indexDocumentWithEmbedding(Model $model): void
    {
        // $this->ensureSearchable($model);

        // Prima generiamo l'embedding, poi indichiamo
        Bus::chain([
            new GenerateEmbeddingsJob($model),
            new IndexInSearchJob($model)
        ])->dispatch();
    }

    public function bulkIndex(iterable $models): void
    {
        if (!count($models)) {
            return;
        }

        $firstModel = $models[0] ?? $models->first();
        // $this->ensureSearchable($firstModel);

        BulkIndexSearchJob::dispatch(collect($models), $firstModel->searchableAs());
    }

    protected function checkIndexExists(Model $model): bool
    {
        $client = $this->createClient();
        $indexName = $this->getIndexName($model);

        return $client->indices()->exists(['index' => $indexName])->asBool();
    }

    public function createIndex(Model $model): void
    {
        // $this->ensureSearchable($model);

        $client = $this->createClient();
        $indexName = $this->getIndexName($model);
        $tempIndex = $indexName . '_temp_' . time();

        try {
            // Otteniamo il mapping dal modello
            $mapping = [];
            if (method_exists($model, 'getSearchMapping')) {
                $mapping = $model->getSearchMapping();
            } else if (method_exists($model, 'toSearchableIndex')) {
                $mapping = $model->toSearchableIndex();
            }

            $indexConfig = [
                'index' => $indexName,
                'body' => [
                    'mappings' => [
                        'properties' => $mapping
                    ]
                ]
            ];

            $indexExists = $client->indices()->exists(['index' => $indexName]);

            // Se l'indice non esiste, lo creiamo
            if (!$indexExists) {
                $client->indices()->create($indexConfig);
                Log::info("Indice Elasticsearch '{$indexName}' creato");
                return;
            }

            // Altrimenti, creiamo un indice temporaneo e reindicizziamo
            $indexConfig['index'] = $tempIndex;
            $client->indices()->create($indexConfig);

            // Reindicizziamo i documenti
            $client->reindex([
                'body' => [
                    'source' => ['index' => $indexName],
                    'dest' => ['index' => $tempIndex]
                ]
            ]);

            // Eliminiamo il vecchio indice e assegnamo l'alias
            $client->indices()->delete(['index' => $indexName]);
            $client->indices()->putAlias([
                'index' => $tempIndex,
                'name' => $indexName
            ]);

            Log::info("Indice Elasticsearch '{$indexName}' aggiornato");
        } catch (\Exception $e) {
            // Pulizia in caso di errori
            if ($client->indices()->exists(['index' => $tempIndex])) {
                $client->indices()->delete(['index' => $tempIndex]);
            }

            Log::error("Creazione indice Elasticsearch '{$indexName}' fallita", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function search(string $query, array $options = []): array
    {
        $client = $this->createClient();

        $params = [
            'index' => $options['index'] ?? null,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [],
                        'minimum_should_match' => 1
                    ]
                ]
            ]
        ];

        // Configurazione dimensione risultati
        if (isset($options['size'])) {
            $params['size'] = $options['size'];
        }

        // Configurazione paginazione
        if (isset($options['from'])) {
            $params['from'] = $options['from'];
        }

        // Query di ricerca testuale
        if ($query !== '' && $query !== null) {
            $params['body']['query']['bool']['should'][] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $options['fields'] ?? ['*'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO'
                ]
            ];
        }

        // Filtri
        if (isset($options['filters']) && $options['filters'] !== []) {
            $filters = $this->buildSearchFilters($options['filters']);
            if ($filters !== []) {
                $params['body']['query']['bool']['must'] = array_merge(
                    $params['body']['query']['bool']['must'],
                    $filters
                );
            }
        }

        // Ordinamento
        if (isset($options['sort'])) {
            $params['body']['sort'] = $options['sort'];
        }

        // Solo campi specifici
        if (isset($options['_source'])) {
            $params['body']['_source'] = $options['_source'];
        }

        // Esecuzione query
        $results = $client->search($params);

        return $results->asArray();
    }

    public function vectorSearch(array $vector, array $options = []): array
    {
        $client = $this->createClient();

        $params = [
            'index' => $options['index'] ?? null,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [
                            [
                                'script_score' => [
                                    'query' => ['match_all' => new \stdClass()],
                                    'script' => [
                                        'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
                                        'params' => ['query_vector' => $vector]
                                    ]
                                ]
                            ]
                        ],
                        'minimum_should_match' => 1
                    ]
                ]
            ]
        ];

        // Configurazione dimensione risultati
        if (isset($options['size'])) {
            $params['size'] = $options['size'];
        }

        // Configurazione paginazione
        if (isset($options['from'])) {
            $params['from'] = $options['from'];
        }

        // Filtri
        if (isset($options['filters']) && $options['filters'] !== []) {
            $filters = $this->buildSearchFilters($options['filters']);
            if ($filters !== []) {
                $params['body']['query']['bool']['must'] = array_merge(
                    $params['body']['query']['bool']['must'],
                    $filters
                );
            }
        }

        // Ordinamento
        $params['body']['sort'] = $options['sort'] ?? ['_score' => ['order' => 'desc']];

        // Solo campi specifici
        if (isset($options['_source'])) {
            $params['body']['_source'] = $options['_source'];
        }

        // Esecuzione query
        $results = $client->search($params);

        return $results->asArray();
    }

    public function buildSearchFilters(array $filters): array|string
    {
        $esFilters = [];

        foreach ($filters as $field => $value) {
            if (is_array($value) && isset($value['type'])) {
                // Filtri avanzati con tipo esplicito
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
                                $field => $value['point']
                            ]
                        ];
                        break;
                }
            } elseif (is_array($value) && count($value) === 2 && isset($value[0]) && isset($value[1])) {
                // Assumiamo che sia un range
                $esFilters[] = [
                    'range' => [
                        $field => [
                            'gte' => $value[0],
                            'lte' => $value[1]
                        ]
                    ]
                ];
            } else {
                // Filtro semplice per valore esatto
                $esFilters[] = ['term' => [$field => $value]];
            }
        }

        return $esFilters;
    }

    public function reindexModel(string $modelClass): void
    {
        ReindexSearchJob::dispatch($modelClass);
    }

    public function syncModel(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Class {$modelClass} does not exist");
        }

        if (!$this->usesSearchableTrait(new $modelClass())) {
            throw new \InvalidArgumentException("Model {$modelClass} does not implement the Searchable trait");
        }

        $query = $modelClass::query();

        // Supporto per soft delete
        if (method_exists($modelClass, 'withTrashed')) {
            $query->withTrashed();
        }

        // Filtri
        if ($id) {
            $query->where('id', $id);
        } elseif ($from) {
            $query->where('updated_at', '>', Carbon::parse($from));
        } else {
            $lastIndexed = (new $modelClass())->getLastIndexedTimestamp();
            if ($lastIndexed) {
                $query->where('updated_at', '>', $lastIndexed);
            }
        }

        $count = $query->count();

        // Se non ci sono record, non facciamo niente
        if ($count === 0) {
            return 0;
        }

        // Sincronizziamo ogni record
        $query->chunk(100, function ($records) {
            foreach ($records as $record) {
                $this->indexDocument($record);
            }
        });

        return $count;
    }

    /**
     * Ottieni il nome dell'indice per il modello
     */
    protected function getIndexName(Model $model): string
    {
        $indexName = $model->searchableAs();

        // Aggiungi prefisso se configurato
        if ($this->config['index_prefix'] !== '' && $this->config['index_prefix'] !== null) {
            $indexName = $this->config['index_prefix'] . $indexName;
        }

        return $indexName;
    }

    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array
    {
        $client = $this->createClient();

        $query = [
            'index' => $this->getIndexName($model),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]]
                        ]
                    ]
                ],
                'aggs' => [
                    'over_time' => [
                        'date_histogram' => [
                            'field' => $filters['date_field'] ?? 'valid_from',
                            'calendar_interval' => $interval
                        ]
                    ]
                ]
            ]
        ];

        // Aggiungi filtri se presenti
        if ($filters !== [] && $filters !== null) {
            $esFilters = $this->buildSearchFilters($filters);

            if ($esFilters !== []) {
                $query['body']['query']['bool']['must'] = array_merge(
                    $query['body']['query']['bool']['must'],
                    $esFilters
                );
            }
        }

        $response = $client->search($query);
        return $response['aggregations']['over_time']['buckets'] ?? [];
    }

    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array
    {
        $client = $this->createClient();

        $query = [
            'index' => $this->getIndexName($model),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]]
                        ]
                    ]
                ],
                'aggs' => [
                    'by_term' => [
                        'terms' => [
                            'field' => $field,
                            'size' => $size
                        ]
                    ]
                ]
            ]
        ];

        // Aggiungi filtri se presenti
        if ($filters !== [] && $filters !== null) {
            $esFilters = $this->buildSearchFilters($filters);

            if ($esFilters !== []) {
                $query['body']['query']['bool']['must'] = array_merge(
                    $query['body']['query']['bool']['must'],
                    $esFilters
                );
            }
        }

        $response = $client->search($query);
        return $response['aggregations']['by_term']['buckets'] ?? [];
    }
d
    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array
    {
        $client = $this->createClient();

        $query = [
            'index' => $this->getIndexName($model),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]]
                        ]
                    ]
                ],
                'aggs' => [
                    'geo_clusters' => [
                        'geohash_grid' => [
                            'field' => $geoField,
                            'precision' => 5
                        ]
                    ]
                ]
            ]
        ];

        // Aggiungi filtri se presenti
        if ($filters !== [] && $filters !== null) {
            $esFilters = $this->buildSearchFilters($filters);

            if ($esFilters !== []) {
                $query['body']['query']['bool']['must'] = array_merge(
                    $query['body']['query']['bool']['must'],
                    $esFilters
                );
            }
        }

        $response = $client->search($query);
        return $response['aggregations']['geo_clusters']['buckets'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array
    {
        $client = $this->createClient();

        $query = [
            'index' => $this->getIndexName($model),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]]
                        ]
                    ]
                ],
                'aggs' => [
                    'field_stats' => [
                        'stats' => [
                            'field' => $field
                        ]
                    ]
                ]
            ]
        ];

        // Aggiungi filtri se presenti
        if ($filters !== [] && $filters !== null) {
            $esFilters = $this->buildSearchFilters($filters);

            if ($esFilters !== []) {
                $query['body']['query']['bool']['must'] = array_merge(
                    $query['body']['query']['bool']['must'],
                    $esFilters
                );
            }
        }

        $response = $client->search($query);
        return $response['aggregations']['field_stats'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getHistogram(Model $model, string $field, array $filters = [], $interval = 50): array
    {
        $client = $this->createClient();

        $query = [
            'index' => $this->getIndexName($model),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['entity' => $model->getTable()]]
                        ]
                    ]
                ],
                'aggs' => [
                    'histogram' => [
                        'histogram' => [
                            'field' => $field,
                            'interval' => $interval
                        ]
                    ]
                ]
            ]
        ];

        // Aggiungi filtri se presenti
        if ($filters !== [] && $filters !== null) {
            $esFilters = $this->buildSearchFilters($filters);

            if ($esFilters !== []) {
                $query['body']['query']['bool']['must'] = array_merge(
                    $query['body']['query']['bool']['must'],
                    $esFilters
                );
            }
        }

        $response = $client->search($query);
        return $response['aggregations']['histogram']['buckets'] ?? [];
    }
}
