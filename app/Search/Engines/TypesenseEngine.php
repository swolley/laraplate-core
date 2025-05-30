<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laravel\Scout\Engines\TypesenseEngine as BaseTypesenseEngine;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Jobs\BulkIndexSearchJob;
use Modules\Core\Search\Jobs\GenerateEmbeddingsJob;
use Modules\Core\Search\Jobs\IndexInSearchJob;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\Searchable;
use Override;

/**
 * Implementation of the search engine for Typesense.
 */
class TypesenseEngine extends BaseTypesenseEngine implements ISearchEngine
{
    public array $config;

    public function supportsVectorSearch(): bool
    {
        return true;
    }

    public function index(Model|Collection $model): void
    {
        $this->ensureSearchable($model);
        IndexInSearchJob::dispatch($model);
    }

    public function indexDocumentWithEmbedding(Model|Collection $model): void
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

    public function createIndex($name, array $options = []): void
    {
        $this->ensureSearchable($name);

        $client = app('typesense');
        $collection = $this->getIndexName($name);

        try {
            // Get mapping from model
            $schema = [];

            if (method_exists($name, 'getSearchMapping')) {
                $schema = $name->getSearchMapping();
            } elseif (method_exists($name, 'toSearchableIndex')) {
                $schema = $name->toSearchableIndex();
            }

            // Add collection name to schema
            $schema['name'] = $collection;

            // Check if collection exists
            try {
                $client->collections[$collection]->retrieve();
                Log::info("Typesense collection '{$collection}' already exists");
            } catch (Exception) {
                // Collection doesn't exist, create it
                $client->collections->create($schema);
                Log::info("Typesense collection '{$collection}' created");
            }
        } catch (Exception $e) {
            Log::error("Error creating Typesense collection '{$collection}'", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function vectorSearch(array $vector, array $options = [])
    {
        $collection = $options['index'] ?? null;

        $searchParams = [
            'q' => '*',
            'vector_query' => 'embedding:(' . implode(',', $vector) . ')',
            'per_page' => $options['size'] ?? 10,
        ];

        if (isset($options['filter'])) {
            $searchParams['filter_by'] = $this->buildTypesenseFilter($options['filter']);
        }

        return $this->typesense->collections[$collection]->documents->search($searchParams);
    }

    public function buildSearchFilters(array $filters): string
    {
        $filterStrings = [];

        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                if (count($value) === 2 && is_numeric($value[0]) && is_numeric($value[1])) {
                    // Range filter
                    $filterStrings[] = "{$field}:>={$value[0]} && {$field}:<={$value[1]}";
                } else {
                    // IN filter
                    $values = implode(',', array_map(fn ($val) => is_string($val) ? "\"{$val}\"" : $val, $value));
                    $filterStrings[] = "{$field}:[{$values}]";
                }
            } else {
                // Exact match
                $formattedValue = is_string($value) ? "\"{$value}\"" : $value;
                $filterStrings[] = "{$field}:={$formattedValue}";
            }
        }

        return implode(' && ', $filterStrings);
    }

    #[Override]
    public function reindex(string $modelClass): void
    {
        ReindexSearchJob::dispatch($modelClass);
    }

    #[Override]
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

    public function getTimeBasedMetrics(Model $model, array $filters = [], string $interval = '1M'): array
    {
        $collection = $this->getIndexName($model);

        $searchParams = [
            'q' => '*',
            'group_by' => $filters['date_field'] ?? 'valid_from',
            'group_limit' => 100,
        ];

        if (isset($filters['filter'])) {
            $searchParams['filter_by'] = $this->buildTypesenseFilter($filters['filter']);
        }

        $response = $this->typesense->collections[$collection]->documents->search($searchParams);

        return $response['grouped_hits'] ?? [];
    }

    public function getTermBasedMetrics(Model $model, string $field, array $filters = [], int $size = 10): array
    {
        $collection = $this->getIndexName($model);

        $searchParams = [
            'q' => '*',
            'group_by' => $field,
            'group_limit' => $size,
        ];

        if (isset($filters['filter'])) {
            $searchParams['filter_by'] = $this->buildTypesenseFilter($filters['filter']);
        }

        $response = $this->typesense->collections[$collection]->documents->search($searchParams);

        return $response['grouped_hits'] ?? [];
    }

    public function getGeoBasedMetrics(Model $model, string $geoField = 'geocode', array $filters = []): array
    {
        $collection = $this->getIndexName($model);

        $searchParams = [
            'q' => '*',
            'group_by' => $geoField,
            'group_limit' => 100,
        ];

        if (isset($filters['filter'])) {
            $searchParams['filter_by'] = $this->buildTypesenseFilter($filters['filter']);
        }

        $response = $this->typesense->collections[$collection]->documents->search($searchParams);

        return $response['grouped_hits'] ?? [];
    }

    public function getNumericFieldStats(Model $model, string $field, array $filters = []): array
    {
        $collection = $this->getIndexName($model);

        $searchParams = [
            'q' => '*',
            'group_by' => $field,
            'group_limit' => 1,
        ];

        if (isset($filters['filter'])) {
            $searchParams['filter_by'] = $this->buildTypesenseFilter($filters['filter']);
        }

        $response = $this->typesense->collections[$collection]->documents->search($searchParams);

        return $response['grouped_hits'][0] ?? [];
    }

    public function getHistogram(Model $model, string $field, array $filters = [], $interval = 50): array
    {
        $collection = $this->getIndexName($model);

        $searchParams = [
            'q' => '*',
            'group_by' => $field,
            'group_limit' => 100,
        ];

        if (isset($filters['filter'])) {
            $searchParams['filter_by'] = $this->buildTypesenseFilter($filters['filter']);
        }

        $response = $this->typesense->collections[$collection]->documents->search($searchParams);

        return $response['grouped_hits'] ?? [];
    }

    // #[Override]
    // public function index(Model|Collection $model): void
    // {
    //     $collection = $this->getIndexName($model);
    //     $this->typesense->collections[$collection]->update($model->toSearchableArray());
    // }

    #[Override]
    public function indexWithEmbedding(Model|Collection $model): void
    {
        $collection = $this->getIndexName($model);
        $this->typesense->collections[$collection]->update($model->toSearchableArray());
    }

    #[Override]
    public function getSearchMapping(Model $model): array
    {
        // TODO: Implement getSearchMapping() method.
    }

    #[Override]
    public function prepareDataToEmbed(): ?string
    {
        // TODO: Implement prepareDataToEmbed() method.
    }

    #[Override]
    public function ensureIndex(Model $model): bool
    {
        // TODO: Implement ensureIndex() method.
    }

    #[Override]
    public function getLastIndexedTimestamp(Model $model): ?string
    {
        // TODO: Implement getLastIndexedTimestamp() method.
    }

    #[Override]
    public function checkIndex(Model $model): bool
    {
        $collection = $this->getIndexName($model);

        try {
            $this->typesense->collections[$collection]->retrieve();

            return true;
        } catch (Exception) {
            return false;
        }
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
}
