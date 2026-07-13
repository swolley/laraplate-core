<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Contracts\IQueryIntentParser;
use Modules\Core\Search\Contracts\ISearchPlanner;
use Modules\Core\Search\Contracts\ITextEmbedder;
use Modules\Core\Search\DTOs\AdvancedSearchResult;

final readonly class AdvancedSearchService
{
    public function __construct(
        private IQueryIntentParser $intent_parser,
        private ISearchPlanner $planner,
        private EnsembleSearchService $ensemble_search,
        private Application $app,
    ) {}

    public function available(?Model $model = null): bool
    {
        $engine = $model instanceof Model ? $this->engineFor($model) : null;

        if (! $engine instanceof ISearchEngine) {
            return false;
        }

        return $engine->supportsOrchestratedSearch();
    }

    /**
     * @param  array<int, \Modules\Core\Casts\Sort>  $sort
     */
    public function search(Model $model, string $query, int $page, int $perPage, ?FiltersGroup $filters = null, array $sort = []): AdvancedSearchResult
    {
        $engine = $this->engineFor($model);

        if (! $engine instanceof ISearchEngine || ! $engine->supportsOrchestratedSearch()) {
            return AdvancedSearchResult::empty($page, $perPage, ['unsupported_driver' => true]);
        }

        $intent = $this->intent_parser->parse($query);
        $search_query = $this->expandedQuery($intent, $query);
        $plan = $this->planner->safePlan($query);
        $plan['intent'] = $intent;
        $plan['retrieval']['size'] = $perPage;
        $plan = $this->applyEngineCapabilities($engine, $plan);
        $vector = $this->resolveVector($query, $plan);

        return $this->ensemble_search->search(
            model: $model,
            query: $search_query,
            plan: $plan,
            vector: $vector,
            page: $page,
            perPage: $perPage,
            filters: $filters,
            sort: $sort,
        );
    }

    private function engineFor(Model $model): ?ISearchEngine
    {
        if (! method_exists($model, 'searchableUsing')) {
            return null;
        }

        $engine = $model->searchableUsing();

        return $engine instanceof ISearchEngine ? $engine : null;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function applyEngineCapabilities(ISearchEngine $engine, array $plan): array
    {
        $retrieval = $this->planSection($plan, 'retrieval');

        if (($retrieval['use_vector'] ?? false) !== true) {
            return $plan;
        }

        if ($engine->supportsOrchestratedVectorSearch()) {
            return $plan;
        }

        $plan['retrieval'] = $retrieval;
        $plan['retrieval']['use_vector'] = false;
        $plan['retrieval']['use_ensemble'] = false;

        $plan['ensemble'] = $this->planSection($plan, 'ensemble');
        $plan['ensemble']['keyword_weight'] = 1.0;
        $plan['ensemble']['vector_weight'] = 0.0;
        $plan['ensemble']['hybrid_weight'] = 0.0;

        $plan['vector'] = $this->planSection($plan, 'vector');
        $plan['vector']['enabled'] = false;

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function planSection(array $plan, string $key): array
    {
        $section = $plan[$key] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<float>|null
     */
    private function resolveVector(string $query, array $plan): ?array
    {
        $retrieval = is_array($plan['retrieval'] ?? null) ? $plan['retrieval'] : [];

        if (($retrieval['use_vector'] ?? false) !== true || ! $this->app->bound(ITextEmbedder::class)) {
            return null;
        }

        /** @var ITextEmbedder $embedder */
        $embedder = $this->app->make(ITextEmbedder::class);

        return $embedder->embed($query);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function expandedQuery(array $intent, string $fallback): string
    {
        $query = $intent['query'] ?? null;

        if (! is_array($query)) {
            return $fallback;
        }

        $expanded = $query['expanded'] ?? null;

        return is_string($expanded) && $expanded !== '' ? $expanded : $fallback;
    }
}
