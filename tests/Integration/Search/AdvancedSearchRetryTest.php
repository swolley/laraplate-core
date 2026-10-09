<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\DTOs\SearchStrategy;
use Modules\Core\Search\Enums\SearchMode;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Modules\Core\Tests\Stubs\Search\EngineBoundStubModel;
use Modules\Core\Tests\Stubs\Search\FixedSearchStrategyResolver;

/**
 * @return list<array{id: string, score: float, source: array<string, mixed>}>
 */
function retry_hits(int $count): array
{
    return array_map(
        static fn (int $i): array => ['id' => 'doc-' . $i, 'score' => 3.0, 'source' => []],
        $count === 0 ? [] : range(1, $count),
    );
}

/**
 * @param  list<int>  $hit_counts  hits each successive ensemble call returns
 * @param  list<array<string, mixed>>  $plans  receives the plan of each ensemble call
 * @param  list<string>  $queries  receives the query of each ensemble call
 */
function retry_service(array $hit_counts, int $max_retries, array &$plans, array &$queries): AdvancedSearchService
{
    $call = 0;
    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble->shouldReceive('search')->andReturnUsing(
        function (Model $model, string $query, array $plan) use (&$call, &$plans, &$queries, $hit_counts): AdvancedSearchResult {
            $plans[] = $plan;
            $queries[] = $query;
            $hits = retry_hits($hit_counts[min($call++, count($hit_counts) - 1)]);

            return new AdvancedSearchResult($hits, count($hits), 1, 10, 1, ['strategies_executed' => 1]);
        },
    );

    $deep = new SearchStrategy(
        applied_mode: SearchMode::Deep,
        planner: new FallbackSearchPlanner,
        reranker: new HeuristicReranker,
        intent_parser: new SimpleQueryIntentParser,
        max_retries: $max_retries,
    );

    return new AdvancedSearchService(new FixedSearchStrategyResolver(deep: $deep), $ensemble, app());
}

function retry_model(): EngineBoundStubModel
{
    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    $engine->shouldReceive('supportsOrchestratedVectorSearch')->andReturnTrue();

    return new EngineBoundStubModel($engine);
}

it('searches again with a wider net when the first result is poor and the caller allows it', function (): void {
    $plans = [];
    $queries = [];
    $service = retry_service([2, 8], 2, $plans, $queries);

    $result = $service->search(retry_model(), 'query', 1, 10, mode: SearchMode::Deep, retries: 2);

    expect($plans)->toHaveCount(2)
        ->and($plans[1]['retrieval']['size'])->toBeGreaterThan($plans[0]['retrieval']['size'])
        ->and($result->hits)->toHaveCount(8)
        ->and($result->meta['search']['retries_used'])->toBe(1);
});

it('does not retry a result that is good enough', function (): void {
    $plans = [];
    $queries = [];
    $result = retry_service([10], 2, $plans, $queries)->search(retry_model(), 'query', 1, 10, mode: SearchMode::Deep, retries: 2);

    expect($plans)->toHaveCount(1)->and($result->meta['search']['retries_used'])->toBe(0);
});

it('does not retry when the caller did not ask for it', function (): void {
    $plans = [];
    $queries = [];
    $result = retry_service([1], 2, $plans, $queries)->search(retry_model(), 'query', 1, 10, mode: SearchMode::Deep);

    expect($plans)->toHaveCount(1)->and($result->meta['search']['retries_used'])->toBe(0);
});

it('caps the retries at what the strategy allows', function (): void {
    $plans = [];
    $queries = [];
    $result = retry_service([1], 1, $plans, $queries)->search(retry_model(), 'query', 1, 10, mode: SearchMode::Deep, retries: 5);

    expect($plans)->toHaveCount(2)->and($result->meta['search']['retries_used'])->toBe(1);
});

it('never retries on a strategy with no retries, whatever the caller asks', function (): void {
    $plans = [];
    $queries = [];
    $result = retry_service([1], 0, $plans, $queries)->search(retry_model(), 'query', 1, 10, mode: SearchMode::Deep, retries: 3);

    expect($plans)->toHaveCount(1)->and($result->meta['search']['retries_used'])->toBe(0);
});

it('keeps the first result when the retry found less', function (): void {
    $plans = [];
    $queries = [];
    $result = retry_service([3, 1], 1, $plans, $queries)->search(retry_model(), 'query', 1, 10, mode: SearchMode::Deep, retries: 1);

    expect($result->hits)->toHaveCount(3)->and($result->meta['search']['retries_used'])->toBe(1);
});

it('searches the words of the user on a retry, not the expansion', function (): void {
    $plans = [];
    $queries = [];
    retry_service([1, 1], 1, $plans, $queries)->search(retry_model(), 'plain words', 1, 10, mode: SearchMode::Deep, retries: 1);

    expect($queries[1])->toBe('plain words');
});
