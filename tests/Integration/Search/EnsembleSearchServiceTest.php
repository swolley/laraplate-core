<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Contracts\ISearchable;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SearchQueryAnalyzer;
use Modules\Core\Search\Services\TextMatchOptionsResolver;
use Modules\Core\Search\Traits\CommonEngineFunctions;
use Modules\Core\Tests\Integration\Search\EnsembleSearchPaginatorTestModel;
use Modules\Core\Tests\Stubs\Search\FusionFixtureSearchModel;
use Modules\Core\Tests\Stubs\Search\FusionFixtureTranslatedSearchModel;

beforeEach(function (): void {
    $this->reranker = new HeuristicReranker();
    $this->service = new EnsembleSearchService($this->reranker);
});

it('accepts IReranker in constructor', function (): void {
    $mock_reranker = Mockery::mock(IReranker::class);
    $service = new EnsembleSearchService($mock_reranker);

    expect($service)->toBeInstanceOf(EnsembleSearchService::class);
});

it('resolves vector fields from searchable mappings through the existing engine layer', function (): void {
    $engine = new class implements ISearchable
    {
        use CommonEngineFunctions;

        public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
        {
            return 0;
        }

        public function buildSearchFilters(array $filters): array|string
        {
            return [];
        }

        public function getSearchMapping(Model $model): array
        {
            return [];
        }

        public function checkIndex(string|Model $model): bool
        {
            return true;
        }

        public function reindex(string $modelClass): void {}
    };
    $model = new class extends Model
    {
        /**
         * @return array<string, mixed>
         */
        public function getSearchMapping(): array
        {
            return [
                'fields' => [
                    ['name' => 'semantic_vector', 'type' => 'float[]'],
                ],
            ];
        }
    };
    $builder = new ScoutBuilder($model, '*');
    $builder->wheres['semantic_vector'] = [0.1, 0.2, 0.3];

    $ref = new ReflectionClass($engine);
    $resolve = $ref->getMethod('resolveVectorField');
    $resolve->setAccessible(true);
    $extract = $ref->getMethod('extractVectorFromBuilder');
    $extract->setAccessible(true);

    expect($resolve->invoke($engine, $model))->toBe('semantic_vector')
        ->and($extract->invoke($engine, $builder))->toBe([0.1, 0.2, 0.3]);
});

it('uses scout pagination total for strategy totals while fetching the fusion window', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;

    $result = $this->service->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'needle',
        plan: [
            'retrieval' => [
                'use_fulltext' => true,
                'use_vector' => false,
            ],
            'ensemble' => [],
            'ranking' => ['use_reranker' => false],
        ],
        vector: null,
        page: 3,
        perPage: 5,
    );

    expect($result->total)->toBe(27)
        ->and($result->totalPages)->toBe(6)
        ->and($result->hits)->toHaveCount(5)
        ->and(EnsembleSearchPaginatorTestModel::$lastBuilder?->getCalled)->toBeFalse()
        ->and(EnsembleSearchPaginatorTestModel::$lastBuilder?->paginatedPerPage)->toBe(15)
        ->and(EnsembleSearchPaginatorTestModel::$lastBuilder?->paginatedPage)->toBe(1);
});

it('degrades to fused results when the reranker throws', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;

    $throwing = Mockery::mock(IReranker::class);
    $throwing->shouldReceive('score')->andThrow(new RuntimeException('reranker service down'));

    $result = (new EnsembleSearchService($throwing))->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'needle',
        plan: [
            'retrieval' => ['use_fulltext' => true, 'use_vector' => false],
            'ensemble' => [],
            'ranking' => ['use_reranker' => true],
        ],
        vector: null,
        page: 1,
        perPage: 5,
    );

    expect($result->hits)->not->toBeEmpty()
        ->and($result->meta['reranked'])->toBeFalse();
});

it('does not rerank by default when the plan does not specify the flag', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;

    $result = $this->service->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'needle',
        plan: [
            'retrieval' => [
                'use_fulltext' => true,
                'use_vector' => false,
            ],
            'ensemble' => [],
            'ranking' => [],
        ],
        vector: null,
        page: 1,
        perPage: 5,
    );

    expect($result->meta['reranked'])->toBeFalse();
});

it('reranks when the reranker setting is on and the plan does not specify the flag', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;
    config()->set('core.search.reranker.enabled', true);

    $result = $this->service->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'needle',
        plan: [
            'retrieval' => [
                'use_fulltext' => true,
                'use_vector' => false,
            ],
            'ensemble' => [],
            'ranking' => [],
        ],
        vector: null,
        page: 1,
        perPage: 5,
    );

    expect($result->meta['reranked'])->toBeTrue();
});

it('exposes normalized raw and diagnostic score metadata for fused hits', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;

    $result = $this->service->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'needle',
        plan: [
            'retrieval' => [
                'use_fulltext' => true,
                'use_vector' => false,
            ],
            'ensemble' => [],
            'ranking' => ['use_reranker' => false],
        ],
        vector: null,
        page: 1,
        perPage: 5,
    );

    expect($result->hits)->not->toBeEmpty();

    $hit = $result->hits[0];

    expect($hit)->toHaveKeys(['id', 'score', 'raw_score', 'score_details', 'source'])
        ->and($hit['score'])->toBeFloat()
        ->and($hit['raw_score'])->toBeFloat()
        ->and($hit['score_details'])->toMatchArray([
            'driver' => 'fake',
            'strategy' => 'keyword',
            'rank' => 1,
            'raw_score' => 1.0,
            'normalized_score' => $hit['score'],
        ])
        ->and($hit['score_details']['defaulted'])->toBeFalse()
        ->and($hit['score_details']['strategies'])->toHaveKey('keyword')
        ->and($hit['score_details']['strategies']['keyword']['normalized_score'])->toBe(1.0);
});

it('exposes per-strategy ranked lists on meta for retrieval-quality evaluation', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;

    $result = $this->service->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'needle',
        plan: [
            'retrieval' => [
                'use_fulltext' => true,
                'use_vector' => false,
            ],
            'ensemble' => [],
            'ranking' => ['use_reranker' => false],
        ],
        vector: null,
        page: 1,
        perPage: 5,
    );

    expect($result->meta)->toHaveKey('per_strategy')
        ->and($result->meta['per_strategy'])->toHaveKey('keyword')
        ->and($result->meta['per_strategy']['keyword'])->not->toBeEmpty();

    $first = array_values($result->meta['per_strategy']['keyword'])[0];

    expect($first)->toHaveKeys(['id', 'score', 'rank']);
});

it('propagates one resolved text match decision and exposes matching metadata', function (): void {
    EnsembleSearchPaginatorTestModel::$lastBuilder = null;
    $resolved = (new TextMatchOptionsResolver(new SearchQueryAnalyzer()))->resolve('Mario Rossi');

    $result = $this->service->search(
        model: new EnsembleSearchPaginatorTestModel(),
        query: 'Mario Rossi',
        plan: [
            'retrieval' => ['use_fulltext' => true, 'use_vector' => false],
            'ensemble' => [],
            'ranking' => ['use_reranker' => false],
        ],
        vector: null,
        page: 1,
        perPage: 5,
        textMatch: $resolved,
    );

    expect(EnsembleSearchPaginatorTestModel::$lastBuilder?->options['text_match'])
        ->toMatchArray([
            'max_edits' => 1,
            'operator' => 'and',
            'minimum_should_match' => 100,
            'fuzzy_token_limit' => 1,
        ])->and($result->meta['matching'])->toMatchArray([
            'requested_preference' => 'auto',
            'effective_preference' => 'balanced',
            'significant_token_count' => 2,
            'protected_token_count' => 0,
            'fuzzy_token_limit' => 1,
            'degraded' => ['capabilities'],
        ]);
});

/**
 * @return array<string, mixed>
 */
function ensemble_fusion_fixture_plan(bool $useReranker, array $ranking = []): array
{
    return [
        'retrieval' => ['use_fulltext' => true, 'use_vector' => true],
        'ensemble' => [
            'keyword_weight' => 0.30,
            'vector_weight' => 0.40,
            'hybrid_weight' => 0.30,
            'agreement_boost' => 0.15,
            'rrf_k' => 60,
            'rrf_weight' => 0.25,
        ],
        'ranking' => ['use_reranker' => $useReranker, 'rerank_top_k' => 4, ...$ranking],
    ];
}

function ensemble_fusion_fixture_reranker(): IReranker
{
    $scores = [
        'invoice approval workflow' => 0.20,
        'supplier invoice list' => 0.95,
        'payment reminders' => 0.40,
        'archived invoices' => 0.10,
        'overdue supplier payments' => 0.85,
        'vendor onboarding' => 0.05,
    ];
    $reranker = Mockery::mock(IReranker::class);
    $reranker->shouldReceive('score')->andReturnUsing(
        static fn (array $pairs): array => array_map(static fn (array $pair): float => $scores[$pair['text']], $pairs),
    );

    return $reranker;
}

/**
 * @return list<array{0: string, 1: float}>
 */
function ensemble_fusion_fixture_ranking(EnsembleSearchService $service, array $plan, ?Model $model = null): array
{
    $result = $service->search(
        model: $model ?? new FusionFixtureSearchModel(),
        query: 'supplier invoices',
        plan: $plan,
        vector: [0.1, 0.2, 0.3],
        page: 1,
        perPage: 10,
    );

    return array_map(static fn (array $hit): array => [$hit['id'], $hit['score']], $result->hits);
}

it('fuses keyword, vector and hybrid rankings into a pinned order and pinned scores', function (): void {
    $service = new EnsembleSearchService(ensemble_fusion_fixture_reranker());

    expect(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(false)))->toBe([
        ['3', 0.918035],
        ['2', 0.896436],
        ['1', 0.575374],
        ['5', 0.477169],
        ['4', 0.003906],
        ['6', 0.003906],
    ]);
});

it('blends reranker scores into the fused top-k with pinned results', function (): void {
    $service = new EnsembleSearchService(ensemble_fusion_fixture_reranker());

    expect(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(true)))->toBe([
        ['2', 0.881854],
        ['5', 0.659066],
        ['3', 0.587543],
        ['1', 0.340314],
        ['4', 0.003906],
        ['6', 0.003906],
    ]);
});

it('keeps the fused order when the plan sets a zero rerank blend', function (): void {
    $service = new EnsembleSearchService(ensemble_fusion_fixture_reranker());

    expect(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(true, ['rerank_blend' => 0.0])))
        ->toBe(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(false)));
});

it('reads the rerank blend from the reranker weight setting when the plan does not set it', function (): void {
    $service = new EnsembleSearchService(ensemble_fusion_fixture_reranker());
    config()->set('core.search.reranker.weight', 0.0);

    expect(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(true)))
        ->toBe(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(false)));

    config()->set('core.search.reranker.weight', 0.6);

    expect(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(true)))->toBe([
        ['2', 0.881854],
        ['5', 0.659066],
        ['3', 0.587543],
        ['1', 0.340314],
        ['4', 0.003906],
        ['6', 0.003906],
    ]);
});

/**
 * The hits of a model with translated text carry none of it, so the reranker used to be handed an empty
 * text for every hit and returned the fused order whatever model scored the pairs.
 */
afterEach(function (): void {
    FusionFixtureTranslatedSearchModel::reset();
});

it('reranks on the text the model provides when its hits carry none', function (): void {
    $service = new EnsembleSearchService(ensemble_fusion_fixture_reranker());

    expect(ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(true), new FusionFixtureTranslatedSearchModel()))->toBe([
        ['2', 0.881854],
        ['5', 0.659066],
        ['3', 0.587543],
        ['1', 0.340314],
        ['4', 0.003906],
        ['6', 0.003906],
    ]);
});

it('asks the model for the text in the language the search is requested in', function (): void {
    LocaleContext::set('it');
    $service = new EnsembleSearchService(ensemble_fusion_fixture_reranker());

    ensemble_fusion_fixture_ranking($service, ensemble_fusion_fixture_plan(true), new FusionFixtureTranslatedSearchModel());

    expect(FusionFixtureTranslatedSearchModel::$requestedLocales)->toBe(['it']);
});

it('does not rerank when no hit has any text, and says so instead of scoring empty pairs', function (): void {
    FusionFixtureTranslatedSearchModel::$hasText = false;
    $reranker = Mockery::mock(IReranker::class);
    $reranker->shouldNotReceive('score');
    $service = new EnsembleSearchService($reranker);

    $result = $service->search(
        model: new FusionFixtureTranslatedSearchModel(),
        query: 'supplier invoices',
        plan: ensemble_fusion_fixture_plan(true),
        vector: [0.1, 0.2, 0.3],
        page: 1,
        perPage: 10,
    );

    expect($result->meta['reranked'])->toBeFalse()
        ->and(array_column($result->hits, 'id'))->toBe(['3', '2', '1', '5', '4', '6']);
});
