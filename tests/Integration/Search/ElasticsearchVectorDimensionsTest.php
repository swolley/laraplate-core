<?php

declare(strict_types=1);

use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Services\ElasticsearchService;
use Modules\Core\Tests\Stubs\Search\VectorMappingStubModel;

/**
 * ES-gated: skips when Elasticsearch is not the configured engine or is unreachable.
 */
function vector_dimensions_engine(): ElasticsearchEngine
{
    $engine = (new VectorMappingStubModel)->searchableUsing();

    if (! $engine instanceof ElasticsearchEngine) {
        test()->markTestSkipped('Elasticsearch is not the configured scout engine.');
    }

    try {
        $engine->health();
    } catch (Throwable $e) {
        test()->markTestSkipped('Elasticsearch is not reachable: ' . $e->getMessage());
    }

    return $engine;
}

it('reports the dimensions of the indexed embeddings vector and null for a missing index', function (): void {
    $engine = vector_dimensions_engine();
    $service = ElasticsearchService::getInstance();
    $model = new VectorMappingStubModel();

    try {
        try {
            $service->deleteIndex(VectorMappingStubModel::INDEX);
        } catch (Throwable) {
            // already absent
        }

        expect($engine->indexedVectorDimensions($model))->toBeNull();

        $service->client->indices()->create([
            'index' => VectorMappingStubModel::INDEX,
            'body' => ['mappings' => ['properties' => ['embeddings' => ['properties' => [
                'vector' => ['type' => 'dense_vector', 'dims' => 384, 'index' => true, 'similarity' => 'cosine'],
            ]]]]],
        ]);

        expect($engine->indexedVectorDimensions($model))->toBe(384);
    } finally {
        try {
            $service->deleteIndex(VectorMappingStubModel::INDEX);
        } catch (Throwable) {
            // best-effort cleanup
        }
    }
});
