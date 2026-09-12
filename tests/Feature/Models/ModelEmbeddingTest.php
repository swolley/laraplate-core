<?php

declare(strict_types=1);

use Modules\Core\Models\ModelEmbedding;

// Feature: es-multilingual-index-and-multimodel-embeddings, Task 10
it('scopes embeddings by model_key and locale', function (): void {
    ModelEmbedding::factory()->create(['locale' => 'it', 'model_key' => 'multilingual-e5-small']);

    expect(ModelEmbedding::query()->producedBy('multilingual-e5-small')->forLocale('it')->count())->toBe(1);
});

it('forLocale(null) matches rows with a null locale', function (): void {
    ModelEmbedding::factory()->create(['locale' => null, 'model_key' => 'legacy-model']);
    ModelEmbedding::factory()->create(['locale' => 'en', 'model_key' => 'legacy-model']);

    expect(ModelEmbedding::query()->producedBy('legacy-model')->forLocale(null)->count())->toBe(1);
});

it('producedBy does not match embeddings from a different model_key', function (): void {
    ModelEmbedding::factory()->create(['locale' => 'it', 'model_key' => 'multilingual-e5-small']);
    ModelEmbedding::factory()->create(['locale' => 'it', 'model_key' => 'other-model']);

    expect(ModelEmbedding::query()->producedBy('multilingual-e5-small')->forLocale('it')->count())->toBe(1);
});
