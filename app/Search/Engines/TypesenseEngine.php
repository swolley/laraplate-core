<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Exception;
use Modules\Core\Search\Exceptions\MissingSearchSchemaException;
use Modules\Core\Search\Exceptions\SearchCollectionResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\TypesenseEngine as BaseTypesenseEngine;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\CommonEngineFunctions;
use Modules\Core\Search\Traits\Searchable;
use Override;
use Typesense\Exceptions\TypesenseClientError;

/**
 * Implementation of the search engine for Typesense.
 */
final class TypesenseEngine extends BaseTypesenseEngine implements ISearchEngine
{
    use CommonEngineFunctions;

    //    public array $config;

    public function supportsVectorSearch(): bool
    {
        return true;
    }

    /**
     * @param  array<string,mixed>  $options  Index options
     * @param  bool  $force  Force index creation even if it already exists
     *
     * @throws Exception
     * @throws \Http\Client\Exception
     */
    #[Override]
    public function createIndex(mixed $name, array $options = [], bool $force = false): void
    {
        $collection = is_string($name) ? $name : 'unknown';

        try {
            $matched = $this->matchModelToCollectionName($name);

            throw_if($matched === null, SearchCollectionResolutionException::class, 'Unable to resolve collection name for index creation.');

            $model = $matched['model'];
            $collection = $matched['collection'];

            if (! $force && $this->checkIndex($model)) {
                return;
            }

            if (method_exists($model, 'getSearchMapping')) {
                $schema = $model->getSearchMapping();
            } elseif (method_exists($model, 'toSearchableIndex')) {
                $schema = $model->toSearchableIndex();
            } else {
                throw new MissingSearchSchemaException('No schema definition method found on model ' . $model::class);
            }

            if ($force) {
                // Drop existing collection to ensure schema is up to date
                try {
                    $this->typesense->collections[$collection]->delete();
                } catch (Exception) {
                    // Ignore if it does not exist
                }
            }

            // Add collection name to schema
            $schema['name'] = $collection;

            $this->typesense->collections->create($schema);
            Log::info(sprintf("Typesense collection '%s' created", $collection));
        } catch (Exception $exception) {
            Log::error(sprintf("Error creating Typesense collection '%s'", $collection), [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    #[Override]
    public function search(Builder $builder): mixed
    {
        // Check if it's a vector search.
        if ($this->isVectorSearch($builder)) {
            return $this->performVectorSearch($builder);
        }

        // Otherwise, use the traditional search.
        return parent::search($builder);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    #[Override]
    public function buildSearchFilters(array $filters): string
    {
        $filter_strings = [];

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                if (count($value) === 2 && is_numeric($value[0]) && is_numeric($value[1])) {
                    $filter_strings[] = sprintf('%s:>=%s && %s:<=%s', $field, $value[0], $field, $value[1]);
                } else {
                    $formatted_values = array_map(
                        static function (mixed $val): string {
                            if (is_string($val)) {
                                return sprintf('"%s"', $val);
                            }

                            return is_scalar($val) ? (string) $val : '';
                        },
                        $value,
                    );
                    $filter_strings[] = sprintf('%s:[%s]', $field, implode(',', $formatted_values));
                }
            } else {
                $formatted_value = is_string($value) ? sprintf('"%s"', $value) : (is_scalar($value) ? (string) $value : '');
                $filter_strings[] = sprintf('%s:=%s', $field, $formatted_value);
            }
        }

        return implode(' && ', $filter_strings);
    }

    #[Override]
    public function reindex(string $modelClass): void
    {
        dispatch(new ReindexSearchJob($modelClass));
    }

    #[Override]
    /**
     * @param  class-string<Model&Searchable>  $modelClass
     *
     * @throws \Http\Client\Exception
     * @throws TypesenseClientError
     * @throws JsonException
     */
    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        $this->ensureIndex(new $modelClass());

        $model_instance = new $modelClass();
        $query = $this->newQueryIncludingTrashed($modelClass);

        // Filters
        if ($id !== null && $id !== 0) {
            $query->where('id', $id);
        } elseif (! in_array($from, [null, '', '0'], true)) {
            $query->where('updated_at', '>', Date::parse($from));
        } else {
            $last_indexed = $this->getLastIndexedTimestamp($model_instance);

            if ($last_indexed) {
                $query->where('updated_at', '>', $last_indexed);
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
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws TypesenseClientError
    //     * @throws \Http\Client\Exception
    //     */
    //    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array
    //    {
    //        $collection = $model->searchableAs();
    //
    //        $searchParams = [
    //            'q' => '*',
    //            'group_by' => $filters['date_field'] ?? 'valid_from',
    //            'group_limit' => 100,
    //        ];
    //
    //        if (isset($filters['filter'])) {
    //            $searchParams['filter_by'] = $this->buildFilter($filters['filter']);
    //        }
    //
    //        $response = $this->typesense->collections[$collection]->documents->search($searchParams);
    //
    //        return $response['grouped_hits'] ?? [];
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws TypesenseClientError
    //     * @throws \Http\Client\Exception
    //     */
    //    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array
    //    {
    //        $collection = $model->searchableAs();
    //
    //        $searchParams = [
    //            'q' => '*',
    //            'group_by' => $field,
    //            'group_limit' => $size,
    //        ];
    //
    //        if (isset($filters['filter'])) {
    //            $searchParams['filter_by'] = $this->buildFilter($filters['filter']);
    //        }
    //
    //        $response = $this->typesense->collections[$collection]->documents->search($searchParams);
    //
    //        return $response['grouped_hits'] ?? [];
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws TypesenseClientError
    //     * @throws \Http\Client\Exception
    //     */
    //    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array
    //    {
    //        return $this->getGroupedData($model, $geoField, $filters, 100);
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws TypesenseClientError
    //     * @throws \Http\Client\Exception
    //     */
    //    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array
    //    {
    //        $collection = $model->searchableAs();
    //
    //        $searchParams = [
    //            'q' => '*',
    //            'group_by' => $field,
    //            'group_limit' => 1,
    //        ];
    //
    //        if (isset($filters['filter'])) {
    //            $searchParams['filter_by'] = $this->buildFilter($filters['filter']);
    //        }
    //
    //        $response = $this->typesense->collections[$collection]->documents->search($searchParams);
    //
    //        return $response['grouped_hits'][0] ?? [];
    //    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws TypesenseClientError
    //     * @throws \Http\Client\Exception
    //     */
    //    public function getHistogram(Model $model, string $field, array $filters = [], $interval = 50): array
    //    {
    //        return $this->getGroupedData($model, $field, $filters, 100);
    //    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getSearchMapping(Model $model): array
    {
        // Default mapping for Typesense
        $mapping = [
            'name' => $model->searchableAs(),
            'fields' => [
                'id' => ['name' => 'id', 'type' => 'string', 'index' => true],
                'entity' => ['name' => 'entity', 'type' => 'string', 'facet' => true],
                'connection' => ['name' => 'connection', 'type' => 'string', 'facet' => true],
                self::INDEXED_AT_FIELD => ['name' => self::INDEXED_AT_FIELD, 'type' => 'string', 'sort' => true],
            ],
        ];

        $vector_search_enabled = (bool) config('search.vector_search.enabled', false);

        if ($vector_search_enabled && $this->supportsVectorSearch()) {
            // Typesense vector field configuration
            // The embedding is pre-computed, so we just need to define it as float[]
            $mapping['fields']['embedding'] = [
                'name' => 'embedding',
                'type' => 'float[]',
                'optional' => true,
            ];
        }

        if (method_exists($model, 'getSearchMapping')) {
            $model_additional_mapping = $model->getSearchMapping();
            $fields = $model_additional_mapping['fields'] ?? (Arr::isList($model_additional_mapping) ? $model_additional_mapping : []);

            foreach ($fields as $field) {
                $mapping['fields'][$field['name']] = $field;
            }
        }

        $mapping['fields'] = array_values($mapping['fields']);

        return $mapping;
    }

    /**
     * @param  string|Model|class-string<Model>  $model
     *
     * @throws \Http\Client\Exception
     */
    #[Override]
    public function checkIndex(string|Model $model): bool
    {
        try {
            if ($model instanceof Model) {
                $collection = $this->resolveSearchableCollectionName($model);

                if ($collection === null) {
                    return false;
                }
            } elseif (class_exists($model)) {
                $collection = new $model()->searchableAs();
            } else {
                $collection = $model;
            }

            $this->typesense->collections[$collection]->retrieve();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    //    /**
    //     * @param  Model&Searchable  $model
    //     *
    //     * @throws TypesenseClientError
    //     * @throws \Http\Client\Exception
    //     *
    //     * @return array|mixed
    //     */
    //    private function getGroupedData(Model $model, string $field, array $filters, int $limit): mixed
    //    {
    //        $collection = $model->searchableAs();
    //
    //        $searchParams = [
    //            'q' => '*',
    //            'group_by' => $field,
    //            'group_limit' => $limit,
    //        ];
    //
    //        if (isset($filters['filter'])) {
    //            $searchParams['filter_by'] = $this->buildFilter($filters['filter']);
    //        }
    //
    //        $response = $this->typesense->collections[$collection]->documents->search($searchParams);
    //
    //        return $response['grouped_hits'] ?? [];
    //    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function health(): array
    {
        $health = $this->typesense->health->retrieve();
        $metrics = $this->typesense->metrics->retrieve();

        return [
            'status' => $health['ok'] ? 'success' : 'danger',
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function stats(): array
    {
        return $this->typesense->collections->retrieve();
    }

    /**
     * @throws \Http\Client\Exception
     * @throws TypesenseClientError
     */
    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function performVectorSearch(Builder $builder): mixed
    {
        $model = $builder->model;

        if (! $model instanceof Model) {
            return parent::search($builder);
        }

        $collection = $this->resolveSearchableCollectionName($model);

        if ($collection === null) {
            return parent::search($builder);
        }

        // Extract the vector from the builder
        $vector = $this->extractVectorFromBuilder($builder);

        if ($vector === []) {
            // Fallback to regular search if no vector provided
            return parent::search($builder);
        }

        // Build vector query for Typesense
        // Typesense expects vector_query in format: "field_name:([vector_values])"
        $vectorString = implode(',', array_map(static fn (float|int|string $v): string => (string) $v, $vector));

        $searchParams = [
            'q' => $builder->query ?: '*',
            'vector_query' => sprintf('embedding:(%s)', $vectorString),
            'per_page' => $builder->limit ?: 10,
        ];

        // Add filters if any are present
        if (! empty($builder->wheres)) {
            $filters = $this->buildFiltersFromBuilder($builder);

            if ($filters !== '') {
                $searchParams['filter_by'] = $filters;
            }
        }

        try {
            $response = $this->typesense->collections[$collection]->documents->search($searchParams);

            // Transform results to match Scout's expected format
            $hits = $response['hits'] ?? [];

            if (! is_array($hits)) {
                return collect();
            }

            /** @var list<array<string, mixed>> $normalized_hits */
            $normalized_hits = array_values(array_filter($hits, is_array(...)));

            return collect($normalized_hits)->map(static function (array $hit): array {
                $document = is_array($hit['document'] ?? null) ? $hit['document'] : [];
                $document_id = $document['id'] ?? null;
                $document['_id'] = is_scalar($document_id) ? (string) $document_id : null;
                $document['_score'] = is_numeric($hit['text_match'] ?? null) ? (float) $hit['text_match'] : 0.0;

                return $document;
            });
        } catch (TypesenseClientError $e) {
            Log::error('Typesense vector search failed', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function buildFilter(string $field_name, mixed $value): string
    {
        if (is_array($value)) {
            $formatted_values = array_map(
                static function (mixed $val): string {
                    if (is_string($val)) {
                        return sprintf('"%s"', $val);
                    }

                    return is_scalar($val) ? (string) $val : '';
                },
                $value,
            );

            return sprintf('%s:[%s]', $field_name, implode(',', $formatted_values));
        }

        $formatted_value = is_string($value) ? sprintf('"%s"', $value) : (is_scalar($value) ? (string) $value : '');

        return sprintf('%s:=%s', $field_name, $formatted_value);
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    private function buildFiltersFromBuilder(Builder $builder): string
    {
        $filters = [];

        foreach ($builder->wheres as $field => $value) {
            if ($field === 'vector' || $field === 'embedding') {
                continue; // Skip vector fields
            }

            $filters[] = $this->buildFilter($field, $value);
        }

        return implode(' && ', $filters);
    }
}
