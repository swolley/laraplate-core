<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Search\Contracts\ITextEmbedder;
use Modules\Core\Search\Contracts\IVectorSearchAvailability;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Modules\Core\Search\Services\VectorSearchAvailability;
use Modules\Core\Tests\Stubs\Search\FixedSearchStrategyResolver;
use Modules\Core\Tests\Stubs\Search\VectorGuardOrchestratedEngineStub;
use Modules\Core\Tests\Stubs\Search\VectorGuardPlannerStub;
use Modules\Core\Tests\Stubs\Search\VectorGuardStubModel;

beforeEach(function (): void {
    Cache::flush();
    // This file exercises Core's guard, whichever module bound the interface.
    app()->instance(IVectorSearchAvailability::class, new VectorSearchAvailability);
    config()->set('core.search.vector.enabled', true);
    config()->set('core.search.vector.suspended_reason', null);
    config()->set('core.search.vector.dimensions', 384);
    VectorGuardStubModel::$engine = VectorGuardOrchestratedEngineStub::make(384);
});

afterEach(function (): void {
    VectorGuardStubModel::$engine = null;
});

function guarded_search(?array &$seen_vector): AdvancedSearchResult
{
    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble->shouldReceive('search')->once()->andReturnUsing(
        function (...$args) use (&$seen_vector): AdvancedSearchResult {
            $seen_vector = $args[3];

            return AdvancedSearchResult::empty(1, 10, ['strategies_executed' => 1]);
        },
    );

    $service = new AdvancedSearchService(
        new FixedSearchStrategyResolver(planner: new VectorGuardPlannerStub(), intent_parser: new SimpleQueryIntentParser(), embedder: app()->bound(ITextEmbedder::class) ? app(ITextEmbedder::class) : null),
        $ensemble,
        app(),
    );

    return $service->search(new VectorGuardStubModel(), 'semantic query', 1, 10);
}

it('falls back to keywords with the suspended reason and never embeds', function (): void {
    config()->set('core.search.vector.suspended_reason', 'switching');
    $embedder = Mockery::mock(ITextEmbedder::class);
    $embedder->shouldNotReceive('embed');
    app()->instance(ITextEmbedder::class, $embedder);

    $seen = ['sentinel'];
    $result = guarded_search($seen);

    expect($seen)->toBeNull()
        ->and($result->meta['vector_disabled'])->toBe('suspended')
        ->and($result->meta['strategies_executed'])->toBe(1);
});

it('falls back to keywords with the dimension_mismatch reason', function (): void {
    VectorGuardStubModel::$engine = VectorGuardOrchestratedEngineStub::make(768);
    $embedder = Mockery::mock(ITextEmbedder::class);
    $embedder->shouldNotReceive('embed');
    app()->instance(ITextEmbedder::class, $embedder);

    $seen = ['sentinel'];
    $result = guarded_search($seen);

    expect($seen)->toBeNull()->and($result->meta['vector_disabled'])->toBe('dimension_mismatch');
});

it('embeds the query and adds no meta when the guard says yes', function (): void {
    $embedder = Mockery::mock(ITextEmbedder::class);
    $embedder->shouldReceive('embed')->once()->andReturn([0.1, 0.2]);
    app()->instance(ITextEmbedder::class, $embedder);

    $seen = null;
    $result = guarded_search($seen);

    expect($seen)->toBe([0.1, 0.2])->and($result->meta)->not->toHaveKey('vector_disabled');
});

it('keeps the keyword results when the embedder throws', function (): void {
    $embedder = Mockery::mock(ITextEmbedder::class);
    $embedder->shouldReceive('embed')->once()->andThrow(new RuntimeException('embedding service down'));
    app()->instance(ITextEmbedder::class, $embedder);

    $seen = ['sentinel'];
    $result = guarded_search($seen);

    expect($seen)->toBeNull()->and($result->meta['strategies_executed'])->toBe(1);
});
