<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\DTOs\SearchStrategy;
use Modules\Core\Search\Enums\SearchMode;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SearchCostGuard;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Modules\Core\Tests\Stubs\Search\EngineBoundStubModel;
use Modules\Core\Tests\Stubs\Search\FixedSearchStrategyResolver;

/**
 * @param  array<string, mixed>  $validated
 */
function cost_parsed(array $validated): Modules\Core\Casts\SearchRequestData
{
    $request = SearchRequest::create('/core/api/search/settings', 'GET', $validated);
    $route = new Route('GET', '/core/api/search/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);
    $validator = Mockery::mock(Illuminate\Contracts\Validation\Validator::class);
    $validator->shouldReceive('validated')->andReturn(['qs' => 'john', ...$validated]);
    $request->setValidator($validator);

    return $request->parsed();
}

it('reads a missing cost as fast and no retry', function (): void {
    $parsed = cost_parsed([]);

    expect($parsed->cost)->toBe(SearchMode::Fast)->and($parsed->retry)->toBe(0);
});

it('reads the three costs', function (string $value, SearchMode $expected): void {
    expect(cost_parsed(['cost' => $value])->cost)->toBe($expected);
})->with([['fast', SearchMode::Fast], ['balanced', SearchMode::Balanced], ['deep', SearchMode::Deep]]);

it('reads an unknown cost as fast without an error', function (): void {
    expect(cost_parsed(['cost' => 'turbo'])->cost)->toBe(SearchMode::Fast);
});

it('keeps the path mode apart from the cost', function (): void {
    $parsed = cost_parsed(['mode' => 'orchestrated', 'cost' => 'deep']);

    expect($parsed->mode)->toBe(Modules\Core\Casts\SearchMode::Orchestrated)->and($parsed->cost)->toBe(SearchMode::Deep);
});

it('validates retry as a non-negative integer and cost as a string', function (): void {
    $rules = (new SearchRequest)->rules();

    expect($rules['retry'])->toContain('integer')->toContain('min:0')
        ->and($rules['cost'])->toContain('string');
});

it('clamps retries to the cap held in settings instead of rejecting them', function (): void {
    config()->set('core.search.max_retries', 2);
    $guard = new SearchCostGuard;

    expect($guard->clampRetries(9999))->toBe(2)
        ->and($guard->clampRetries(1))->toBe(1)
        ->and($guard->clampRetries(-3))->toBe(0);

    config()->set('core.search.max_retries', 0);

    expect($guard->clampRetries(5))->toBe(0);
});

it('seeds the cost settings in the search group', function (): void {
    $definitions = collect(Modules\Core\Database\Seeders\CoreDatabaseSeeder::runtimeSettingDefinitions())->keyBy('name');

    expect($definitions->get('search.max_retries')['value'])->toBe(2)
        ->and($definitions->get('search.deep_per_minute')['value'])->toBe(6)
        ->and($definitions->get('search.max_retries')['group_name'])->toBe('search');
});

it('lets a person start deep searches up to the limit, then refuses without throwing', function (): void {
    config()->set('core.search.deep_per_minute', 2);
    RateLimiter::clear('search-deep:user:1');
    $guard = new SearchCostGuard;

    expect($guard->allowsDeep('user:1'))->toBeTrue()
        ->and($guard->allowsDeep('user:1'))->toBeTrue()
        ->and($guard->allowsDeep('user:1'))->toBeFalse()
        ->and($guard->allowsDeep('user:2'))->toBeTrue();
});

it('serves a withheld deep request as fast and says why', function (): void {
    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble->shouldReceive('search')->andReturnUsing(
        static fn (Model $model, string $query, array $plan, ?array $vector, int $page, int $perPage): AdvancedSearchResult => AdvancedSearchResult::empty($page, $perPage, ['strategies_executed' => 1]),
    );
    $deep = new SearchStrategy(
        applied_mode: SearchMode::Deep,
        planner: new FallbackSearchPlanner,
        reranker: new HeuristicReranker,
        intent_parser: new SimpleQueryIntentParser,
        max_retries: 2,
    );
    $service = new AdvancedSearchService(new FixedSearchStrategyResolver(deep: $deep), $ensemble, app());
    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    $engine->shouldReceive('supportsOrchestratedVectorSearch')->andReturnTrue();

    $meta = $service->search(new EngineBoundStubModel($engine), 'q', 1, 10, mode: SearchMode::Deep, retries: 2, withheld_reason: SearchCostGuard::RATE_LIMITED)->meta['search'];

    expect($meta)->toBe([
        'mode_requested' => 'deep',
        'mode_applied' => 'fast',
        'degraded_reason' => 'rate_limited',
        'retries_used' => 0,
    ]);
});

it('puts meta.search in the response so a client reads the mode that was applied', function (): void {
    $search = ['mode_requested' => 'deep', 'mode_applied' => 'fast', 'degraded_reason' => 'rate_limited', 'retries_used' => 0];
    $builder = (new Modules\Core\Helpers\ResponseBuilder(Illuminate\Http\Request::create('/x', 'GET')))->setData([])->setSearchMeta($search);

    expect($builder->getResponse()->getData(true)['meta']['search'])->toBe($search);
});
