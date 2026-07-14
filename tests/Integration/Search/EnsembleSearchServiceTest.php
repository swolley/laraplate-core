<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Contracts\ISearchable;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SearchQueryAnalyzer;
use Modules\Core\Search\Services\TextMatchOptionsResolver;
use Modules\Core\Search\Traits\CommonEngineFunctions;
use Modules\Core\Tests\Integration\Search\EnsembleSearchPaginatorTestModel;

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
