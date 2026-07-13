<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Illuminate\Database\Eloquent\Model;
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
        return $this->getDatabaseDriver() !== 'sqlite';
    }

    public function supportsOrchestratedSearch(): bool
    {
        return true;
    }

    public function supportsOrchestratedVectorSearch(): bool
    {
        return false;
    }

    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function search(Builder $builder): mixed
    {
        if ($this->isVectorSearch($builder)) {
            return $this->performVectorSearch($builder);
        }

        return parent::search($builder);
    }

    /**
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performVectorSearch(Builder $builder): array
    {
        $query_vector = $this->extractVectorFromBuilder($builder);
        $model = $builder->model;
        $driver = $this->getDatabaseDriver();

        return match ($driver) {
            'pgsql' => $this->performPostgreSQLVectorSearch($query_vector, $model, $builder),
            'mysql', 'mariadb' => $this->performMySQLVectorSearch($query_vector, $model, $builder),
            'sqlite' => $this->performSQLiteVectorSearch($query_vector, $model, $builder),
            default => throw new InvalidArgumentException('Vector search not supported for driver: ' . $driver),
        };
    }

    /**
     * @param  list<float>  $queryVector
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performPostgreSQLVectorSearch(array $queryVector, Model $model, Builder $builder): array
    {
        $vector_string = '[' . implode(',', $queryVector) . ']';

        /** @var list<array<string, mixed>> */
        return ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->selectRaw('*, embedding <=> ?::vector AS distance', [$vector_string])
            ->orderByRaw('embedding <=> ?::vector', [$vector_string])
            ->limit($builder->limit ?? 10)
            ->get()
            ->toArray();
    }

    /**
     * @param  list<float>  $queryVector
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performMySQLVectorSearch(array $queryVector, Model $model, Builder $builder): array
    {
        $query_vector_json = json_encode($queryVector);

        /** @var list<array<string, mixed>> */
        return ModelEmbedding::query()
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
            ->orderBy('similarity_score', 'desc')
            ->limit($builder->limit ?? 10)
            ->get()
            ->toArray();
    }

    /**
     * @param  list<float>  $queryVector
     * @param  Builder<covariant Model>  $builder
     * @return list<array<string, mixed>>
     */
    private function performSQLiteVectorSearch(array $queryVector, Model $model, Builder $builder): array
    {
        $limit = (int) ($builder->limit ?? 10);

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
