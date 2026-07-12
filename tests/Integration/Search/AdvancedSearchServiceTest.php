<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Laravel\Scout\Engines\Engine;

function advanced_search_service_with_ensemble(EnsembleSearchService $ensemble): AdvancedSearchService
{
    return new AdvancedSearchService(
        new SimpleQueryIntentParser(),
        new FallbackSearchPlanner(),
        $ensemble,
        app(),
    );
}

it('is available when the model uses a core search engine', function (): void {
    config()->set('scout.driver', 'elasticsearch');

    $service = advanced_search_service_with_ensemble(Mockery::mock(EnsembleSearchService::class));
    $supported_engine = Mockery::mock(ISearchEngine::class);
    $supported_engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    $unsupported_engine = Mockery::mock(Engine::class);
    $database_engine = Mockery::mock(ISearchEngine::class);
    $database_engine->shouldReceive('supportsOrchestratedSearch')->andReturnFalse();

    $supported = new class($supported_engine) extends Model
    {
        public function __construct(private readonly mixed $engine = null)
        {
            parent::__construct();
        }

        public function searchableUsing(): mixed
        {
            return $this->engine;
        }
    };
    $unsupported = new class($unsupported_engine) extends Model
    {
        public function __construct(private readonly mixed $engine = null)
        {
            parent::__construct();
        }

        public function searchableUsing(): mixed
        {
            return $this->engine;
        }
    };
    $database = new class($database_engine) extends Model
    {
        public function __construct(private readonly mixed $engine = null)
        {
            parent::__construct();
        }

        public function searchableUsing(): mixed
        {
            return $this->engine;
        }
    };

    expect($service->available($supported))->toBeTrue()
        ->and($service->available($unsupported))->toBeFalse()
        ->and($service->available($database))->toBeFalse();
});

it('executes ensemble search with parsed intent and plan through the existing engine layer', function (): void {
    config()->set('scout.driver', 'typesense');

    $filters = new FiltersGroup([new Filter('tenant_id', 10)]);
    $result = new AdvancedSearchResult(
        hits: [['id' => '42', 'score' => 1.0, 'source' => ['title' => 'Alpha']]],
        total: 12,
        page: 2,
        perPage: 7,
        totalPages: 3,
        meta: ['strategies_executed' => 1],
    );
    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble
        ->shouldReceive('search')
        ->once()
        ->withArgs(function (Model $model, string $query, array $plan, ?array $vector, int $page, int $perPage, ?FiltersGroup $passed_filters): bool {
            expect($query)->toBe('find alpha')
                ->and($plan['retrieval']['size'])->toBe(7)
                ->and($vector)->toBeNull()
                ->and($page)->toBe(2)
                ->and($perPage)->toBe(7)
                ->and($passed_filters)->toBeInstanceOf(FiltersGroup::class);

            return true;
        })
        ->andReturn($result);

    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    $model = new class($engine) extends Model
    {
        public function __construct(private readonly mixed $engine = null)
        {
            parent::__construct();
        }

        public function searchableUsing(): mixed
        {
            return $this->engine;
        }
    };

    $service = advanced_search_service_with_ensemble($ensemble);
    $actual = $service->search($model, 'find alpha', 2, 7, $filters);

    expect($actual->ids())->toBe(['42'])
        ->and($actual->total)->toBe(12);
});
