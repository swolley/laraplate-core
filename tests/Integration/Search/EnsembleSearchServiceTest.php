<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Search\Contracts\ISearchable;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Traits\CommonEngineFunctions;

class EnsembleSearchPaginatorTestBuilder extends ScoutBuilder
{
    public bool $getCalled = false;

    public ?int $paginatedPerPage = null;

    public ?int $paginatedPage = null;

    /**
     * @param  Collection<int, Model>  $items
     */
    public function __construct(Model $model, string $query, private readonly Collection $items, private readonly int $total)
    {
        parent::__construct($model, $query);
    }

    public function get(): Collection
    {
        $this->getCalled = true;

        return $this->items;
    }

    public function paginate($perPage = null, $pageName = 'page', $page = null): LengthAwarePaginator
    {
        $this->paginatedPerPage = (int) $perPage;
        $this->paginatedPage = (int) ($page ?? 1);

        return new LengthAwarePaginator(
            items: $this->items,
            total: $this->total,
            perPage: (int) $perPage,
            currentPage: $this->paginatedPage,
        );
    }
}

class EnsembleSearchPaginatorTestModel extends Model
{
    public static ?EnsembleSearchPaginatorTestBuilder $lastBuilder = null;

    protected $guarded = [];

    /**
     * @return EnsembleSearchPaginatorTestBuilder
     */
    public static function search($query = '', $callback = null): mixed
    {
        $items = collect(range(1, 15))->map(static function (int $id): self {
            $model = new self();
            $model->forceFill(['id' => $id, '_score' => 1.0]);
            $model->exists = true;

            return $model;
        });

        return self::$lastBuilder = new EnsembleSearchPaginatorTestBuilder(new self(), (string) $query, $items, 27);
    }

    public function searchableUsing(): object
    {
        return new class
        {
            public function getName(): string
            {
                return 'fake';
            }
        };
    }
}

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
