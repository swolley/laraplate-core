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
        if (! $model instanceof Model || ! method_exists($model, 'searchableUsing')) {
            return false;
        }

        $engine = $model->searchableUsing();

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
        if (! $this->available($model)) {
            return AdvancedSearchResult::empty($page, $perPage, ['unsupported_driver' => true]);
        }

        $intent = $this->intent_parser->parse($query);
        $search_query = $this->expandedQuery($intent, $query);
        $plan = $this->planner->safePlan($query);
        $plan['intent'] = $intent;
        $plan['retrieval']['size'] = $perPage;
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
