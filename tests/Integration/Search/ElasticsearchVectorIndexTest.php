<?php

declare(strict_types=1);

use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Services\ElasticsearchService;
use Modules\Core\Tests\Stubs\Search\VectorMappingStubModel;

/**
 * ES-gated: exercises the real ElasticsearchEngine::createIndex against a live
 * cluster using a dedicated throwaway index. Skips when Elasticsearch is not the
 * configured engine or is unreachable, so it is a no-op in CI without ES.
 */
it('applies the dense_vector embedding mapping to Elasticsearch on index creation', function (): void {
    $engine = (new VectorMappingStubModel)->searchableUsing();

    if (! $engine instanceof ElasticsearchEngine) {
        $this->markTestSkipped('Elasticsearch is not the configured scout engine.');
    }

    try {
        $engine->health();
    } catch (Throwable $e) {
        $this->markTestSkipped('Elasticsearch is not reachable: ' . $e->getMessage());
    }

    config()->set('search.vector_search.enabled', true);
    config()->set('search.vector_search.dimension', 384);

    $service = ElasticsearchService::getInstance();
    $index = VectorMappingStubModel::INDEX;

    try {
        $engine->createIndex(VectorMappingStubModel::class, [], true);

        $mapping = $service->client->indices()->getMapping(['index' => $index])->asArray();
        $embedding = $mapping[$index]['mappings']['properties']['embedding'] ?? null;

        expect($embedding)->not->toBeNull()
            ->and($embedding['type'])->toBe('dense_vector')
            ->and($embedding['dims'])->toBe(384);
    } finally {
        try {
            $service->deleteIndex($index);
        } catch (Throwable) {
            // best-effort cleanup
        }
    }
});
