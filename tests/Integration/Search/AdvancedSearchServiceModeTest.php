<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\IQueryIntentParser;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Contracts\ITextEmbedder;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\DTOs\SearchStrategy;
use Modules\Core\Search\Enums\SearchMode;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Tests\Stubs\Search\EngineBoundStubModel;
use Modules\Core\Tests\Stubs\Search\FixedSearchStrategyResolver;

/**
 * @return array{0: AdvancedSearchService, 1: Closure(): ?IReranker, 2: Mockery\MockInterface&IQueryIntentParser, 3: Mockery\MockInterface&ITextEmbedder, 4: Mockery\MockInterface&IReranker}
 */
function mode_search_service(?string $degraded_reason = null): array
{
    $expensive_intent = Mockery::mock(IQueryIntentParser::class);
    $expensive_embedder = Mockery::mock(ITextEmbedder::class);
    $expensive_reranker = Mockery::mock(IReranker::class);

    $deep = new SearchStrategy(
        applied_mode: $degraded_reason === null ? SearchMode::Deep : SearchMode::Fast,
        planner: new FallbackSearchPlanner,
        reranker: $expensive_reranker,
        intent_parser: $expensive_intent,
        embedder: $expensive_embedder,
        degraded_reason: $degraded_reason,
    );

    $used_reranker = null;
    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble->shouldReceive('search')->andReturnUsing(function (Model $model, string $query, array $plan, ?array $vector, int $page, int $perPage, mixed $filters = null, array $sort = [], mixed $textMatch = null, ?IReranker $reranker = null) use (&$used_reranker): AdvancedSearchResult {
        $used_reranker = $reranker;

        return AdvancedSearchResult::empty(1, 10, ['strategies_executed' => 1]);
    });

    $service = new AdvancedSearchService(
        new FixedSearchStrategyResolver(reranker: new HeuristicReranker, deep: $deep),
        $ensemble,
        app(),
    );

    return [$service, static function () use (&$used_reranker): ?IReranker {
        return $used_reranker;
    }, $expensive_intent, $expensive_embedder, $expensive_reranker];
}

function mode_engine_model(): EngineBoundStubModel
{
    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    $engine->shouldReceive('supportsOrchestratedVectorSearch')->andReturnTrue();

    return new EngineBoundStubModel($engine);
}

it('never touches the expensive components on a fast search', function (): void {
    [$service, $used_reranker, $intent, $embedder] = mode_search_service();
    $intent->shouldNotReceive('parse');
    $embedder->shouldNotReceive('embed');

    $meta = $service->search(mode_engine_model(), 'storia della citta', 1, 10)->meta;

    expect($used_reranker())->toBeInstanceOf(HeuristicReranker::class)
        ->and($meta['search'])->toBe([
            'mode_requested' => 'fast',
            'mode_applied' => 'fast',
            'degraded_reason' => null,
            'retries_used' => 0,
        ]);
});

it('runs a deep search with the components the strategy carries', function (): void {
    [$service, $used_reranker, $intent, , $reranker] = mode_search_service();
    $intent->shouldReceive('parse')->once()->andReturn([]);

    $meta = $service->search(mode_engine_model(), 'storia della citta', 1, 10, mode: SearchMode::Deep)->meta;

    expect($used_reranker())->toBe($reranker)
        ->and($meta['search']['mode_requested'])->toBe('deep')
        ->and($meta['search']['mode_applied'])->toBe('deep');
});

it('reports the reason when a deep request was served as fast', function (): void {
    [$service, , $intent] = mode_search_service('search_orchestration_disabled');
    $intent->shouldReceive('parse')->andReturn([]);

    $meta = $service->search(mode_engine_model(), 'storia della citta', 1, 10, mode: SearchMode::Deep)->meta;

    expect($meta['search'])->toBe([
        'mode_requested' => 'deep',
        'mode_applied' => 'fast',
        'degraded_reason' => 'search_orchestration_disabled',
        'retries_used' => 0,
    ]);
});

it('carries the mode block on the unsupported driver path too', function (): void {
    [$service] = mode_search_service('mode_unavailable');
    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnFalse();

    $meta = $service->search(new EngineBoundStubModel($engine), 'q', 1, 10, mode: SearchMode::Deep)->meta;

    expect($meta['unsupported_driver'])->toBeTrue()
        ->and($meta['search']['mode_requested'])->toBe('deep')
        ->and($meta['search']['mode_applied'])->toBe('fast');
});
