<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Models\ModelEmbedding;

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
