<?php

declare(strict_types=1);

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
