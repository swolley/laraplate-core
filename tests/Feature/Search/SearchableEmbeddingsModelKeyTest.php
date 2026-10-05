<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Support\VectorModelContext;
use Modules\Core\Tests\Stubs\Search\VectorEmbeddingsArrayStubModel;

beforeEach(function (): void {
    Config::set('core.search.vector.enabled', true);

    Schema::create('core_test_vector_embeddings_stub', function ($table): void {
        $table->id();
    });

    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsVectorSearch')->andReturn(true);

    $this->model = new VectorEmbeddingsArrayStubModel();
    $this->model->withEngine($engine);
    $this->model->saveQuietly();

    $this->model->embeddings()->create(['embedding' => [0.1, 0.2], 'locale' => 'it', 'model_key' => 'a:one']);
    $this->model->embeddings()->create(['embedding' => [0.3, 0.4, 0.5], 'locale' => 'it', 'model_key' => 'b:two']);
    $this->model->embeddings()->create(['embedding' => [0.9], 'locale' => 'it', 'model_key' => null]);
});

it('serializes only the vectors of the configured model', function (): void {
    Config::set('core.search.vector.model', 'a:one');

    expect($this->model->toSearchableArray()['embeddings'])->toBe([['vector' => [0.1, 0.2]]]);
});

it('serializes only the vectors of the override inside using()', function (): void {
    Config::set('core.search.vector.model', 'a:one');

    $array = VectorModelContext::using('b:two', fn (): array => $this->model->toSearchableArray());

    expect($array['embeddings'])->toBe([['vector' => [0.3, 0.4, 0.5]]]);
});

it('filters an eager-loaded relation the same way', function (): void {
    Config::set('core.search.vector.model', 'b:two');
    $this->model->load('embeddings');

    expect($this->model->toSearchableArray()['embeddings'])->toBe([['vector' => [0.3, 0.4, 0.5]]]);
});

it('serializes every row when no model is configured', function (): void {
    Config::set('core.search.vector.model', '');

    expect($this->model->toSearchableArray()['embeddings'])->toHaveCount(3);
});
