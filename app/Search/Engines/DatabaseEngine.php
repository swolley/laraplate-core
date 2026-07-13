<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine as BaseDatabaseEngine;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Traits\CommonEngineFunctions;

final class DatabaseEngine extends BaseDatabaseEngine implements ISearchEngine
{
    use CommonEngineFunctions;

    public function checkIndex(string|Model $model): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function createIndex(mixed $name, array $options = [], bool $force = false): void
    {
        parent::createIndex($name, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [];
    }

    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        return 0;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildSearchFilters(array $filters): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchMapping(Model $model): array
    {
        return [];
    }

    public function reindex(string $modelClass): void {}

    public function supportsVectorSearch(): bool
    {
        return true;
    }

    public function supportsOrchestratedSearch(): bool
    {
        return true;
    }

    public function supportsOrchestratedVectorSearch(): bool
    {
        return true;
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function search(Builder $builder): mixed
    {
        if ($this->isVectorSearch($builder)) {
            $models = $this->vectorModels($builder, $builder->limit);

            return [
                'results' => $models,
                'total' => $models->count(),
            ];
        }

        return parent::search($builder);
    }

    public function paginateUsingDatabase(Builder $builder, $perPage, $pageName, $page): LengthAwarePaginator
    {
        if (! $this->isVectorSearch($builder)) {
            return parent::paginateUsingDatabase($builder, $perPage, $pageName, $page);
        }

        $page = (int) ($page ?: Paginator::resolveCurrentPage($pageName));
        $perPage = (int) ($perPage ?: $builder->model->getPerPage());
        $models = $this->vectorModels($builder, PHP_INT_MAX);
        $items = $models
            ->slice(max(0, ($page - 1) * $perPage), $perPage)
            ->values();

        return new LengthAwarePaginator(
            items: $builder->model->newCollection($items->all()),
            total: $models->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        );
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performVectorSearch(Builder $builder, ?int $limit = null): array
    {
        $query_vector = $this->extractVectorFromBuilder($builder);
        $model = $builder->model;
        $driver = $this->getDatabaseDriver();

        return match ($driver) {
            'pgsql' => $this->performPostgreSQLVectorSearch($query_vector, $model, $builder, $limit),
            'mysql', 'mariadb' => $this->performMySQLVectorSearch($query_vector, $model, $builder, $limit),
            'sqlite' => $this->performSQLiteVectorSearch($query_vector, $model, $builder, $limit),
            default => throw new InvalidArgumentException('Vector search not supported for driver: ' . $driver),
        };
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @return EloquentCollection<int, Model>
     */
    private function vectorModels(Builder $builder, ?int $limit = null): EloquentCollection
    {
        $matches = $this->vectorMatches($builder, $limit);
        $ids = array_values(array_unique(array_map(static fn (array $match): mixed => $match['model_id'], $matches)));

        if ($ids === []) {
            return $builder->model->newCollection();
        }

        $models = $this->constrainedVectorModelQuery($builder, $ids)
            ->get()
            ->keyBy(static fn (Model $model): string => (string) $model->getKey());
        $ordered = [];

        foreach ($matches as $match) {
            $model = $models->get((string) $match['model_id']);

            if (! $model instanceof Model) {
                continue;
            }

            $model->setAttribute('_score', $match['score']);
            $ordered[] = $model;
        }

        return $builder->model->newCollection($ordered);
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @return list<array{model_id: mixed, score: float}>
     */
    private function vectorMatches(Builder $builder, ?int $limit = null): array
    {
        $rows = $this->performVectorSearch($builder, $limit);
        $matches = [];

        foreach ($rows as $row) {
            $model_id = $row['model_id'] ?? null;

            if (! is_scalar($model_id)) {
                continue;
            }

            $score = match (true) {
                is_numeric($row['similarity_score'] ?? null) => (float) $row['similarity_score'],
                is_numeric($row['distance'] ?? null) => 1.0 / (1.0 + (float) $row['distance']),
                default => 1.0,
            };

            $matches[] = [
                'model_id' => $model_id,
                'score' => $score,
            ];
        }

        return $matches;
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @param  list<mixed>  $ids
     * @return EloquentBuilder<Model>
     */
    private function constrainedVectorModelQuery(Builder $builder, array $ids): EloquentBuilder
    {
        /** @var EloquentBuilder<Model> $query */
        $query = $builder->model->newQuery()->whereKey($ids);
        $vector_fields = array_flip(['vector', 'embedding', $this->resolveVectorField($builder->model)]);

        foreach (array_diff_key($builder->wheres, $vector_fields) as $key => $value) {
            if ($key === '__soft_deleted') {
                continue;
            }

            $query->where($key, '=', $value);
        }

        foreach ($builder->whereIns as $key => $values) {
            $query->whereIn($key, $values);
        }

        foreach ($builder->whereNotIns as $key => $values) {
            $query->whereNotIn($key, $values);
        }

        return $this->constrainForSoftDeletes($builder, $query);
    }

    /**
     * @param  list<float>  $queryVector
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performPostgreSQLVectorSearch(array $queryVector, Model $model, Builder $builder, ?int $limit = null): array
    {
        $vector_string = '[' . implode(',', $queryVector) . ']';
        $query = ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->selectRaw('*, embedding <=> ?::vector AS distance', [$vector_string])
            ->orderByRaw('embedding <=> ?::vector', [$vector_string]);

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var list<array<string, mixed>> */
        return $query
            ->get()
            ->toArray();
    }

    /**
     * @param  list<float>  $queryVector
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performMySQLVectorSearch(array $queryVector, Model $model, Builder $builder, ?int $limit = null): array
    {
        $query_vector_json = json_encode($queryVector);
        $query = ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->selectRaw(
                "*, 
                (
                    SELECT SUM(a.value * b.value) / 
                    (SQRT(SUM(a.value * a.value)) * SQRT(SUM(b.value * b.value)))
                    FROM JSON_TABLE(?, '$[*]' COLUMNS (value DOUBLE PATH '$')) a,
                         JSON_TABLE(embedding, '$[*]' COLUMNS (value DOUBLE PATH '$')) b
                ) AS similarity_score",
                [$query_vector_json],
            )
            ->having('similarity_score', '>', 0.7)
            ->orderBy('similarity_score', 'desc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var list<array<string, mixed>> */
        return $query
            ->get()
            ->toArray();
    }

    /**
     * @param  list<float>  $queryVector
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performSQLiteVectorSearch(array $queryVector, Model $model, Builder $builder, ?int $limit = null): array
    {
        $limit ??= (int) ($builder->limit ?? 10);

        if ($limit <= 0) {
            return [];
        }

        /** @var list<array{id: int|null, similarity_score: float, embedding: list<float>}> $results */
        $results = [];

        foreach (ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->lazy(100) as $embedding) {
            $stored_embedding = $embedding->embedding ?? [];

            if (! is_array($stored_embedding)) {
                $stored_embedding = [];
            }

            /** @var list<float> $normalized_embedding */
            $normalized_embedding = array_values(array_map(
                static fn (mixed $value): float => (float) $value,
                $stored_embedding,
            ));

            $similarity = $this->calculateCosineSimilarity($queryVector, $normalized_embedding);

            if ($similarity <= 0.7) {
                continue;
            }

            $results[] = [
                'id' => $embedding->id,
                'model_id' => $embedding->model_id,
                'similarity_score' => $similarity,
                'embedding' => $normalized_embedding,
            ];

            usort($results, static fn (array $left, array $right): int => $right['similarity_score'] <=> $left['similarity_score']);

            if (count($results) > $limit) {
                array_pop($results);
            }
        }

        return $results;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function calculateCosineSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot_product = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;
        $counter = count($a);

        for ($i = 0; $i < $counter; $i++) {
            $dot_product += $a[$i] * $b[$i];
            $norm_a += $a[$i] * $a[$i];
            $norm_b += $b[$i] * $b[$i];
        }

        if ($norm_a === 0.0 || $norm_b === 0.0) {
            return 0.0;
        }

        return $dot_product / (sqrt($norm_a) * sqrt($norm_b));
    }

    private function getDatabaseDriver(): string
    {
        $default_connection = config('database.default');

        return is_string($default_connection) ? $default_connection : 'mysql';
    }
}
