<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Laravel\Scout\EngineManager;
use Modules\Core\Services\ElasticsearchService;
use Elastic\ScoutDriverPlus\Searchable as ScoutSearchable;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Extended searchable trait that supports multiple engines
 * Provides enhanced functionality for Elasticsearch and Typesense
 * 
 * Note: Models using this trait should implement SearchableInterface
 */
trait Searchable
{
    use ScoutSearchable;

    /**
     * Field name for indexing timestamp
     */
    public const INDEXED_AT_FIELD = 'indexed_at';

    /**
     * Extends the standard method with support for embeddings and other data
     */
    public function toSearchableArray(): array
    {
        // Start with basic searchable array from Scout
        // $array = $this->toArray();
        $array = [];

        // find on web, typesense requires id to be a string
        if (config('scout.driver') === 'typesense') {
            $array['id'] = (string) $this->getKey();
        } else {
            $array['id'] = $this->getKey();
        }

        // Add common data
        $array['connection'] = $this->getConnectionName() ?: 'default';
        $array['entity'] = $this->getTable();
        $array[self::INDEXED_AT_FIELD] = now()->utc()->toZuluString();

        // Add embeddings if available
        if (method_exists($this, 'embeddings')) {
            $embeddings = $this->embeddings()->get()->pluck('embedding')->toArray();
            if (!empty($embeddings)) {
                $array['embedding'] = $embeddings[0] ?? []; // Use first embedding
            }
        }

        return $array;
    }

    /**
     * Prepare text data for embedding generation
     */
    public function prepareDataToEmbed(): ?string
    {
        if (!isset($this->embed) || empty($this->embed)) {
            return null;
        }

        $data = "";
        foreach ($this->embed as $attribute) {
            $value = $this->$attribute;
            if ($value && gettype($value) === "string" && ($value !== '' && $value !== '0')) {
                $data .= ' ' . $value;
            }
        }

        return $data;
    }

    /**
     * Generate embeddings for the model
     * 
     * @param bool $force If true, force generation even if embeddings already exist
     * @return void
     */
    public function generateEmbeddings(bool $force = false): void
    {
        if (config('scout.vector_search.enabled')) {
            GenerateEmbeddingsJob::dispatch($this, $force);
        }
    }

    /**
     * Relationship with model embeddings
     */
    public function embeddings(): MorphMany
    {
        return $this->morphMany(\Modules\Core\Models\ModelEmbedding::class, 'model');
    }

    /**
     * Get field mapping for search engine
     * Convert generic field definitions to the format required by the current search engine
     * 
     * @return array Mapping in format appropriate for current search engine
     */
    public function getSearchMapping(): array
    {
        $driver = config('scout.driver');
        $fields = $this->getSearchFields();

        if ($driver === 'elastic') {
            return $this->convertToElasticsearchMapping($fields);
        } elseif ($driver === 'typesense') {
            return $this->convertToTypesenseSchema($fields);
        }

        // Default to Elasticsearch format as fallback
        return $this->convertToElasticsearchMapping($fields);
    }

    /**
     * Get generic search field definitions
     * This provides an engine-agnostic representation of search fields
     * 
     * @return array Array of field definitions with common properties
     */
    protected function getSearchFields(): array
    {
        $fields = [];

        // Process fillable fields
        foreach ($this->getFillable() as $field) {
            $castType = $this->getCasts()[$field] ?? null;

            $fieldData = [
                'name' => $field,
                'type' => $this->getGenericFieldType($castType),
                'sortable' => true,
                'filterable' => true,
                'searchable' => true
            ];

            // Add special handling based on field name or type
            if (in_array($fieldData['type'], ['text', 'string'])) {
                $fieldData['analyzer'] = 'standard';
            }

            $fields[$field] = $fieldData;
        }

        // Add indexed_at timestamp field
        $fields[self::INDEXED_AT_FIELD] = [
            'name' => self::INDEXED_AT_FIELD,
            'type' => 'date',
            'format' => 'yyyy-MM-dd\TH:mm:ss\Z',
            'sortable' => true,
            'filterable' => true,
            'searchable' => false
        ];

        // Add embedding field if model supports it
        if (method_exists($this, 'embeddings')) {
            $fields['embedding'] = [
                'name' => 'embedding',
                'type' => 'vector',
                'dimensions' => 1536, // OpenAI embedding dimensions
                'similarity' => 'cosine',
                'sortable' => false,
                'filterable' => false,
                'searchable' => true
            ];
        }

        return $fields;
    }

    /**
     * Map model cast types to generic field types
     */
    protected function getGenericFieldType(?string $castType): string
    {
        return match ($castType) {
            'integer', 'int' => 'integer',
            'float', 'double', 'decimal' => 'float',
            'boolean', 'bool' => 'boolean',
            'datetime', 'date', 'timestamp' => 'date',
            'array', 'json', 'object', 'collection' => 'object',
            default => 'text'
        };
    }

    /**
     * Convert generic field definitions to Elasticsearch mapping format
     */
    protected function convertToElasticsearchMapping(array $fields): array
    {
        $mapping = [];

        foreach ($fields as $fieldName => $fieldData) {
            $type = $this->mapGenericToElasticsearchType($fieldData['type']);

            $mapping[$fieldName] = ['type' => $type];

            // Handle special field types with additional properties
            if ($fieldData['type'] === 'date' && isset($fieldData['format'])) {
                $mapping[$fieldName]['format'] = $fieldData['format'];
            } elseif ($fieldData['type'] === 'vector') {
                $mapping[$fieldName] = [
                    'type' => 'dense_vector',
                    'dims' => $fieldData['dimensions'],
                    'index' => true,
                    'similarity' => $fieldData['similarity']
                ];
            } elseif ($fieldData['type'] === 'text' && isset($fieldData['analyzer'])) {
                $mapping[$fieldName]['analyzer'] = $fieldData['analyzer'];
            }
        }

        return $mapping;
    }

    /**
     * Convert generic field definitions to Typesense schema format
     */
    protected function convertToTypesenseSchema(array $fields): array
    {
        $typesenseFields = [];
        $defaultSortingField = 'id';

        foreach ($fields as $fieldName => $fieldData) {
            $fieldType = $this->mapGenericToTypesenseType($fieldData['type']);

            $field = [
                'name' => $fieldName,
                'type' => $fieldType
            ];

            // Add faceting for filterable string fields
            if ($fieldData['filterable'] && in_array($fieldType, ['string', 'string[]'])) {
                $field['facet'] = true;
            }

            // Special handling for vector fields
            if ($fieldData['type'] === 'vector') {
                $field = [
                    'name' => $fieldName,
                    'type' => 'float[]',
                    'embed' => [
                        'from' => ['*'],
                        'model_config' => [
                            'model_name' => 'openai'
                        ]
                    ]
                ];
            }

            // For ID field or primary key
            if ($fieldName === 'id' || $fieldName === $this->getKeyName()) {
                $defaultSortingField = $fieldName;
            }

            $typesenseFields[] = $field;
        }

        return [
            'fields' => $typesenseFields,
            'default_sorting_field' => $defaultSortingField
        ];
    }

    /**
     * Map generic field types to Elasticsearch types
     */
    protected function mapGenericToElasticsearchType(string $genericType): string
    {
        return match ($genericType) {
            'integer' => 'long',
            'float' => 'double',
            'boolean' => 'boolean',
            'date' => 'date',
            'object' => 'object',
            'vector' => 'dense_vector', // Will need additional properties
            'text', 'string' => 'text',
            default => 'text'
        };
    }

    /**
     * Map generic field types to Typesense types
     */
    protected function mapGenericToTypesenseType(string $genericType): string
    {
        return match ($genericType) {
            'integer' => 'int32',
            'float' => 'float',
            'boolean' => 'bool',
            'date' => 'string', // Typesense treats dates as strings
            'object' => 'object',
            'vector' => 'float[]', // Will need special handling
            'text', 'string' => 'string',
            default => 'string'
        };
    }

    /**
     * Reindex all records of this model
     */
    public function reindex(): void
    {
        // Use Scout's method
        $this->searchableUsing()->flush($this);
        $this->newQuery()->get()->searchable();
    }

    /**
     * Check if index exists and create if needed
     */
    public function checkIndex(bool $createIfMissing = false): bool
    {
        try {
            $index = $this->searchableAs();
            $exists = false;

            // Determine current driver and check index
            $driver = config('scout.driver');
            $client = app(EngineManager::class)->engine();

            if ($driver === 'elastic') {
                // For Elasticsearch
                $client = ElasticsearchService::getInstance()->getClient();
                $exists = $client->indices()->exists(['index' => $index])->asBool();
            } elseif ($driver === 'typesense') {
                // For Typesense
                $client = app('typesense');
                try {
                    $client->collections[$index]->retrieve();
                    $exists = true;
                } catch (\Exception $e) {
                    $exists = false;
                }
            }

            if (!$exists && $createIfMissing) {
                $this->createIndex();
                return true;
            }

            return $exists;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create or update search index
     */
    public function createIndex(): void
    {
        $driver = config('scout.driver');

        if ($driver === 'elastic') {
            $this->createElasticsearchIndex();
        } elseif ($driver === 'typesense') {
            $this->createTypesenseCollection();
        }
    }

    /**
     * Create Elasticsearch index
     */
    protected function createElasticsearchIndex(): void
    {
        try {
            $client = ElasticsearchService::getInstance()->getClient();
            $index = $this->searchableAs();

            // Check if index exists
            $exists = $client->indices()->exists(['index' => $index])->asBool();

            if (!$exists) {
                // Create index with appropriate mapping
                $client->indices()->create([
                    'index' => $index,
                    'body' => [
                        'mappings' => [
                            'properties' => $this->getSearchMapping()
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            // Log error
            \Illuminate\Support\Facades\Log::error('Error creating Elasticsearch index', [
                'model' => get_class($this),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Create Typesense collection
     */
    protected function createTypesenseCollection(): void
    {
        try {
            $client = app('typesense');
            $collection = $this->searchableAs();

            // Check if collection exists
            try {
                $client->collections[$collection]->retrieve();
                return; // Collection already exists
            } catch (\Exception $e) {
                // Collection doesn't exist, create it
                $schema = $this->convertToTypesenseSchema($this->getSearchFields());
                $schema['name'] = $collection;

                $client->collections->create($schema);
            }
        } catch (\Exception $e) {
            // Log error
            \Illuminate\Support\Facades\Log::error('Error creating Typesense collection', [
                'model' => get_class($this),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get the timestamp of last indexing
     */
    public function getLastIndexedTimestamp(): ?string
    {
        $driver = config('scout.driver');

        if ($driver === 'elastic') {
            return $this->getElasticsearchLastIndexedTimestamp();
        } elseif ($driver === 'typesense') {
            return $this->getTypesenseLastIndexedTimestamp();
        }

        return null;
    }

    /**
     * Get last indexed timestamp from Elasticsearch
     */
    protected function getElasticsearchLastIndexedTimestamp(): ?string
    {
        try {
            $client = ElasticsearchService::getInstance()->getClient();
            $index = $this->searchableAs();

            $response = $client->search([
                'index' => $index,
                'body' => [
                    'size' => 1,
                    'sort' => [self::INDEXED_AT_FIELD => 'desc'],
                    'query' => [
                        'match_all' => (object)[]
                    ]
                ]
            ]);

            return $response->asArray()['hits']['hits'][0]['_source'][self::INDEXED_AT_FIELD] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get last indexed timestamp from Typesense
     */
    protected function getTypesenseLastIndexedTimestamp(): ?string
    {
        try {
            $client = app('typesense');
            $collection = $this->searchableAs();

            $response = $client->collections[$collection]->documents->search([
                'q' => '*',
                'sort_by' => self::INDEXED_AT_FIELD . ':desc',
                'per_page' => 1
            ]);

            return $response['hits'][0]['document'][self::INDEXED_AT_FIELD] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Perform vector search based on embedding
     *
     * @param array $vector Embedding vector
     * @param array $options Search options
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function vectorSearch(array $vector, array $options = [])
    {
        $driver = config('scout.driver');
        $size = $options['size'] ?? 10;

        if ($driver === 'elastic') {
            return static::search()
                ->rawQuery([
                    'script_score' => [
                        'query' => $options['filter'] ?? ['match_all' => new \stdClass()],
                        'script' => [
                            'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
                            'params' => ['query_vector' => $vector]
                        ]
                    ]
                ])
                ->take($size)
                ->get();
        } elseif ($driver === 'typesense') {
            // For Typesense, we use the vector search capability
            $client = app('typesense');
            $collection = $this->searchableAs();

            $searchParams = [
                'q' => '*',
                'vector_query' => 'embedding:(' . implode(',', $vector) . ')',
                'per_page' => $size
            ];

            if (isset($options['filter'])) {
                $searchParams['filter_by'] = $this->buildTypesenseFilter($options['filter']);
            }

            $response = $client->collections[$collection]->documents->search($searchParams);

            // Convert to Eloquent collection
            $ids = collect($response['hits'])->pluck('document.id')->toArray();
            return static::whereIn($this->getKeyName(), $ids)
                ->get()
                ->sortBy(function ($model) use ($ids) {
                    return array_search($model->getKey(), $ids);
                });
        }

        return collect();
    }

    /**
     * Build Typesense filter from array
     */
    protected function buildTypesenseFilter(array $filters): string
    {
        $filterStrings = [];

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                if (count($value) === 2 && is_numeric($value[0]) && is_numeric($value[1])) {
                    // Range filter
                    $filterStrings[] = "$field:>=" . $value[0] . " && $field:<=" . $value[1];
                } else {
                    // IN filter
                    $values = implode(',', array_map(function ($val) {
                        return is_string($val) ? "\"$val\"" : $val;
                    }, $value));
                    $filterStrings[] = "$field:[$values]";
                }
            } else {
                // Exact match
                $formattedValue = is_string($value) ? "\"$value\"" : $value;
                $filterStrings[] = "$field:=$formattedValue";
            }
        }

        return implode(' && ', $filterStrings);
    }
}
