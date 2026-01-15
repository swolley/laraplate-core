<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine as BaseDatabaseEngine;
use Modules\AI\Models\ModelEmbedding;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Traits\CommonEngineFunctions;

final class DatabaseEngine extends BaseDatabaseEngine implements ISearchEngine
{
    use CommonEngineFunctions;

    public function checkIndex(Model $model): bool
    {
        return true;
    }

    public function createIndex($name, array $options = []): void
    {
        parent::createIndex($name, $options);
    }

    // TODO: to be implemented
    public function health(): array
    {
        return [];
    }

    // TODO: to be implemented
    public function stats(): array
    {
        return [];
    }

    // TODO: to be implemented
    public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
    {
        return 0;
    }

    // TODO: to be implemented
    public function buildSearchFilters(array $filters): array
    {
        return [];
    }

    // TODO: to be implemented
    public function getSearchMapping(Model $model): array
    {
        return [];
    }

    // TODO: to be implemented
    public function reindex(string $modelClass): void {}

    public function supportsVectorSearch(): bool
    {
        return $this->getDatabaseDriver() !== 'sqlite'; // SQLite has limitations
    }

    public function search(Builder $builder)
    {
        if ($this->isVectorSearch($builder)) {
            return $this->performVectorSearch($builder);
        }

        return parent::search($builder);
    }

    private function performVectorSearch(Builder $builder): array
    {
        $queryVector = $builder->getVector();
        $model = $builder->model;
        $driver = $this->getDatabaseDriver();

        return match ($driver) {
            'pgsql' => $this->performPostgreSQLVectorSearch($queryVector, $model, $builder),
            'mysql', 'mariadb' => $this->performMySQLVectorSearch($queryVector, $model, $builder),
            'sqlite' => $this->performSQLiteVectorSearch($queryVector, $model, $builder),
            default => throw new InvalidArgumentException('Vector search not supported for driver: ' . $driver),
        };
    }

    private function performPostgreSQLVectorSearch(array $queryVector, Model $model, Builder $builder): array
    {
        // Usa l'estensione pgvector per performance ottimali
        $vectorString = '[' . implode(',', $queryVector) . ']';

        return ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->selectRaw('*, embedding <=> ?::vector AS distance', [$vectorString])
            ->orderByRaw('embedding <=> ?::vector', [$vectorString])
            ->limit($builder->limit ?? 10)
            ->get()
            ->toArray();
    }

    private function performMySQLVectorSearch(array $queryVector, Model $model, Builder $builder): array
    {
        // Implementazione con funzioni JSON di MySQL
        $queryVectorJson = json_encode($queryVector);

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
                [$queryVectorJson],
            )
            ->having('similarity_score', '>', 0.7)
            ->orderBy('similarity_score', 'desc')
            ->limit($builder->limit ?? 10)
            ->get()
            ->toArray();
    }

    private function performSQLiteVectorSearch(array $queryVector, Model $model, Builder $builder): array
    {
        // Implementazione base per SQLite (performance limitate)
        return ModelEmbedding::query()
            ->where('model_type', $model::class)
            ->get()
            ->map(function ($embedding) use ($queryVector): array {
                $similarity = $this->calculateCosineSimilarity($queryVector, $embedding->embedding);

                return [
                    'id' => $embedding->id,
                    'similarity_score' => $similarity,
                    'embedding' => $embedding->embedding,
                ];
            })
            ->filter(static fn ($item): bool => $item['similarity_score'] > 0.7)
            ->sortByDesc('similarity_score')
            ->take($builder->limit ?? 10)
            ->values()
            ->all();
    }

    private function calculateCosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        $counter = count($a);

        for ($i = 0; $i < $counter; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function getDatabaseDriver(): string
    {
        return config('database.default');
    }
}
