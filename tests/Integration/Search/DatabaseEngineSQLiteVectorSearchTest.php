<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Engines\DatabaseEngine;

it('returns the top sqlite vector search matches without changing the result shape', function (): void {
    config()->set('database.default', 'sqlite');

    $now = now();
    $table = CoreTables::ModelEmbeddings->value;

    $insert_embedding = static fn (string $model_type, int $model_id, array $embedding): int => (int) DB::table($table)->insertGetId([
        'model_type' => $model_type,
        'model_id' => $model_id,
        'embedding' => json_encode($embedding, JSON_THROW_ON_ERROR),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $best_match_id = $insert_embedding(ModelEmbedding::class, 10, [1.0, 0.0, 0.0]);
    $second_match_id = $insert_embedding(ModelEmbedding::class, 20, [0.9, 0.1, 0.0]);
    $insert_embedding(ModelEmbedding::class, 30, [0.6, 0.8, 0.0]);
    $insert_embedding(self::class, 40, [1.0, 0.0, 0.0]);

    $builder = new ScoutBuilder(new ModelEmbedding, 'vector:[1,0,0]');
    $builder->limit = 2;

    $method = new ReflectionMethod(DatabaseEngine::class, 'performSQLiteVectorSearch');
    $method->setAccessible(true);

    $results = $method->invoke(new DatabaseEngine, [1.0, 0.0, 0.0], new ModelEmbedding, $builder);

    expect($results)->toHaveCount(2)
        ->and(array_column($results, 'id'))->toBe([$best_match_id, $second_match_id])
        ->and($results[0])->toHaveKeys(['id', 'similarity_score', 'embedding'])
        ->and($results[0]['similarity_score'])->toBeGreaterThan($results[1]['similarity_score'])
        ->and($results[0]['embedding'])->toBe([1.0, 0.0, 0.0]);
});

it('keeps sqlite vector search off full collection map pipelines', function (): void {
    $source = file_get_contents(base_path('Modules/Core/app/Search/Engines/DatabaseEngine.php'));

    expect($source)->not->toContain("->get()\n            ->map(")
        ->and($source)->toContain('->lazy(100)');
});
