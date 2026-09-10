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

/**
 * ES-gated regression: a paginated vector search must order by similarity, not by
 * id. paginate() previously ran only performKeywordSearch(), so vector/hybrid
 * legs (which EnsembleSearchService drives via paginate) silently degraded to a
 * plain keyword query returning documents in id order.
 */
it('orders a paginated vector search by similarity, not by id', function (): void {
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

    $near = array_fill(0, 384, 0.0);
    $near[0] = 1.0; // query points here → doc "2" is the closest
    $far = array_fill(0, 384, 0.0);
    $far[383] = 1.0; // orthogonal → doc "1" is the least similar

    try {
        $engine->createIndex(VectorMappingStubModel::class, [], true);

        // id "1" (lower id) is the LEAST similar; id "2" is the target.
        $service->client->index(['index' => $index, 'id' => '1', 'body' => ['embedding' => $far], 'refresh' => true]);
        $service->client->index(['index' => $index, 'id' => '2', 'body' => ['embedding' => $near], 'refresh' => true]);

        $builder = VectorMappingStubModel::search('*')->where('vector', $near)->take(5);
        $result = $engine->paginate($builder, 5, 1);

        $ids = $result->hits()->map(static fn ($hit): string => (string) $hit->document()->id())->all();

        expect($ids)->not->toBeEmpty()
            ->and($ids[0])->toBe('2'); // similarity order, not id order ("1" first)
    } finally {
        try {
            $service->deleteIndex($index);
        } catch (Throwable) {
            // best-effort cleanup
        }
    }
});
