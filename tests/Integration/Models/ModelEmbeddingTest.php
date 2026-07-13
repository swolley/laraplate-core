<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Models\ModelEmbedding;

function modelEmbeddingsMigrationSource(): string
{
    return (string) file_get_contents(base_path('Modules/Core/database/migrations/2024_11_05_233754_create_model_embeddings_table.php'));
}

it('fillable contains embedding', function (): void {
    $model = new ModelEmbedding;

    expect($model->getFillable())->toBe(['embedding']);
});

it('model returns MorphTo relationship', function (): void {
    $model = new ModelEmbedding;

    expect($model->model())->toBeInstanceOf(MorphTo::class);
});

it('casts embedding to json', function (): void {
    $model = new ModelEmbedding;
    $casts = (new ReflectionMethod(ModelEmbedding::class, 'casts'))->invoke($model);

    expect($casts)->toHaveKey('embedding')
        ->and($casts['embedding'])->toBe('json');
});

// Feature: performance-optimization, Property 21: ModelEmbedding forModel scope uses composite index
it('forModel scope filters on both model_type and model_id', function (): void {
    // Use ModelEmbedding itself as the target model to avoid needing a real model instance
    $target = new ModelEmbedding;
    $target->id = 42;

    $query = ModelEmbedding::query()->forModel($target);

    $sql = $query->toSql();
    $bindings = $query->getBindings();

    expect($sql)
        ->toContain('"model_type"')
        ->toContain('"model_id"');

    expect($bindings)
        ->toContain($target->getMorphClass())
        ->toContain($target->getKey());
});

it('installs and verifies the postgresql vector extension before using vector columns', function (): void {
    $source = modelEmbeddingsMigrationSource();

    expect($source)->toContain('CREATE EXTENSION IF NOT EXISTS vector')
        ->and($source)->toContain('pg_extension')
        ->and($source)->toContain("where('extname', 'vector')");
});

it('uses configurable vector dimensions with an openai compatible fallback', function (): void {
    $source = modelEmbeddingsMigrationSource();

    expect($source)->toContain("'search.vector.dimensions'")
        ->and($source)->toContain('DEFAULT_VECTOR_DIMENSIONS')
        ->and($source)->not->toContain('$table->vector(\'embedding\', 1536)');
});
