<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Exception;
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
     * @param  Model&Searchable  $name
     *
     * @throws Exception
     * @throws \Http\Client\Exception
     */
    #[Override]
    public function createIndex($name, array $options = []): void
    {
        if ($this->checkIndex(new $name())) {
            return;
        }

        $collection = $name->searchableAs();

        try {
            // Get mapping from the model
            // $schema = [];

            // if (method_exists($name, 'getSearchMapping')) {
            $schema = $this->getSearchMapping($name);
            // } elseif (method_exists($name, 'toSearchableIndex')) {
            // $schema = $name->toSearchableIndex();
            // }

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
    public function buildSearchFilters(array $filters): string
    {
        $filterStrings = [];

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                if (count($value) === 2 && is_numeric($value[0]) && is_numeric($value[1])) {
                    // Range filter
                    $filterStrings[] = sprintf('%s:>=%s && %s:<=%s', $field, $value[0], $field, $value[1]);
                } else {
                    // IN filter
                    $values = implode(',', array_map(static fn ($val) => is_string($val) ? sprintf('"%s"', $val) : $val, $value));
                    $filterStrings[] = sprintf('%s:[%s]', $field, $values);
                }
            } else {
                // Exact match
                $formattedValue = is_string($value) ? sprintf('"%s"', $value) : $value;
                $filterStrings[] = sprintf('%s:=%s', $field, $formattedValue);
            }
        }

        return implode(' && ', $filterStrings);
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
     * @throws TypesenseClientError
     * @throws JsonException
     */
    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        $this->ensureIndex(new $modelClass());

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

    #[Override]
    /**
     * @param  Model&Searchable  $model
     */
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

        // Add a vector field if needed
        if (config('search.vector_search.enabled') && $this->supportsVectorSearch()) {
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
     * @param  Model&Searchable  $model
     *
     * @throws \Http\Client\Exception
     */
    #[Override]
    public function checkIndex(Model $model): bool
    {
        $this->ensureSearchable($model);

        try {
            $this->typesense->collections[$model->searchableAs()]->retrieve();

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

    #[Override]
    public function stats(): array
    {
        return $this->typesense->collections->retrieve();
    }

    /**
     * @throws \Http\Client\Exception
     * @throws TypesenseClientError
     */
    private function performVectorSearch(Builder $builder): mixed
    {
        /** @var Model&Searchable $model */
        $model = $builder->model;
        $collection = $model->searchableAs();

        // Extract the vector from the builder
        $vector = $this->extractVectorFromBuilder($builder);

        if ($vector === []) {
            // Fallback to regular search if no vector provided
            return parent::search($builder);
        }

        // Build vector query for Typesense
        // Typesense expects vector_query in format: "field_name:([vector_values])"
        $vectorString = implode(',', array_map(static fn ($v): string => (string) $v, $vector));

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

            return collect($hits)->map(static function (array $hit) {
                $document = $hit['document'] ?? [];
                $document['_id'] = $hit['document']['id'] ?? null;
                $document['_score'] = $hit['text_match'] ?? 0;

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

    private function buildFilter(string $fieldName, mixed $value): string
    {
        if (is_array($value)) {
            $values = implode(',', array_map(static fn ($val): mixed => is_string($val) ? sprintf('"%s"', $val) : $val, $value));

            return sprintf('%s:[%s]', $fieldName, $values);
        }

        $formattedValue = is_string($value) ? sprintf('"%s"', $value) : $value;

        return sprintf('%s:=%s', $fieldName, $formattedValue);
    }

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
