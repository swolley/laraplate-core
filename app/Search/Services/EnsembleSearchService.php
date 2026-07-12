<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\DTOs\AdvancedSearchResult;

/**
 * Ensemble search service that combines keyword, vector and hybrid retrieval
 * strategies with Reciprocal Rank Fusion (RRF) and optional reranking.
 */
class EnsembleSearchService
{
    public function __construct(
        private readonly IReranker $reranker,
        private readonly ?ScoutSearchConstraintApplier $constraint_applier = null,
    ) {}

    /**
     * Execute an ensemble search across multiple retrieval strategies.
     *
     * @param  array<string, mixed>  $plan
     * @param  list<float>|null  $vector
     * @param  array<int, \Modules\Core\Casts\Sort>  $sort
     */
    public function search(Model $model, string $query, array $plan, ?array $vector, int $page, int $perPage, ?FiltersGroup $filters = null, array $sort = []): AdvancedSearchResult
    {
        $retrieval = $this->planSection($plan, 'retrieval');
        $ensemble_config = $this->planSection($plan, 'ensemble');
        $ranking = $this->planSection($plan, 'ranking');

        $use_fulltext = (bool) ($retrieval['use_fulltext'] ?? true);
        $use_vector = (bool) ($retrieval['use_vector'] ?? false) && $vector !== null;
        $window = max(1, $page * $perPage);

        $weights = [
            'keyword' => $this->planFloat($ensemble_config, 'keyword_weight', 0.35),
            'vector' => $this->planFloat($ensemble_config, 'vector_weight', 0.35),
            'hybrid' => $this->planFloat($ensemble_config, 'hybrid_weight', 0.30),
        ];

        $agreement_boost = $this->planFloat($ensemble_config, 'agreement_boost', 0.15);
        $rrf_k = $this->planInt($ensemble_config, 'rrf_k', 60);
        $rrf_weight = $this->planFloat($ensemble_config, 'rrf_weight', 0.25);

        $per_strategy = [];
        $strategy_results = [];

        if ($use_fulltext) {
            $result = $this->executeScoutSearch($model, $query, null, $filters, $sort, $window, $page, $perPage, 'keyword');
            $strategy_results[] = $result;
            $per_strategy['keyword'] = $this->hitsById($result);
        }

        if ($use_vector) {
            $result = $this->executeScoutSearch($model, '*', $vector, $filters, $sort, $window, $page, $perPage, 'vector');
            $strategy_results[] = $result;
            $per_strategy['vector'] = $this->hitsById($result);
        }

        if ($use_fulltext && $use_vector) {
            $result = $this->executeScoutSearch($model, $query, $vector, $filters, $sort, $window, $page, $perPage, 'hybrid');
            $strategy_results[] = $result;
            $per_strategy['hybrid'] = $this->hitsById($result);
        }

        if ($per_strategy === []) {
            return AdvancedSearchResult::empty($page, $perPage, ['strategies_executed' => 0]);
        }

        $adjusted_weights = $this->renormalizeWeightsForExecutedStrategies(
            $weights,
            $use_fulltext,
            $use_vector,
        );

        $fused = $this->fuseStrategies($per_strategy, $adjusted_weights, $agreement_boost, $rrf_k, $rrf_weight);

        $use_reranker = (bool) ($ranking['use_reranker'] ?? config('search.features.reranker', false));
        $default_rerank_top_k = $this->configInt('search.reranker.top_k', 30);
        $rerank_top_k = $this->planInt($ranking, 'rerank_top_k', $default_rerank_top_k);

        if ($use_reranker && $fused !== []) {
            $fused = $this->rerankTopK($fused, $query, $rerank_top_k);
        }

        $hits = array_values(
            collect($fused)
                ->sortByDesc('score')
                ->slice(max(0, ($page - 1) * $perPage), $perPage)
                ->map(fn (array $item): array => [
                    'id' => $item['id'],
                    'score' => round($item['score'], 6),
                    'source' => $item['source'] ?? [],
                ])
                ->all(),
        );
        $total = max(array_map(static fn (AdvancedSearchResult $result): int => $result->total, $strategy_results) ?: [count($hits)]);

        return new AdvancedSearchResult(
            hits: $hits,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: (int) ceil($total / max(1, $perPage)),
            meta: [
                'driver' => method_exists($model->searchableUsing(), 'getName') ? $model->searchableUsing()->getName() : $model->searchableUsing()::class,
                'strategies_executed' => count($per_strategy),
                'strategies' => array_keys($per_strategy),
                'reranked' => $use_reranker,
                'total_results' => count($hits),
            ],
        );
    }

    /**
     * @param  list<float>|null  $vector
     * @param  array<int, \Modules\Core\Casts\Sort>  $sort
     */
    private function executeScoutSearch(Model $model, string $query, ?array $vector, ?FiltersGroup $filters, array $sort, int $window, int $page, int $perPage, string $strategy): AdvancedSearchResult
    {
        /** @var class-string<Model> $model_class */
        $model_class = $model::class;
        /** @var ScoutBuilder<Model> $builder */
        $builder = $model_class::search($query)->take($window);

        if ($vector !== null) {
            $builder->where('vector', $vector);
        }

        $this->constraintApplier()->apply($builder, $model, $filters, $sort);

        $paginator = $builder->paginate($window, 'page', 1);
        $results = $paginator->getCollection();

        return $this->normalizeScoutResults($results, $page, $perPage, $strategy, $paginator->total());
    }

    /**
     * @param  Collection<int, mixed>  $results
     */
    private function normalizeScoutResults(Collection $results, int $page, int $perPage, string $strategy, ?int $total = null): AdvancedSearchResult
    {
        $hits = [];

        foreach ($results as $result) {
            if ($result instanceof Model) {
                $hits[] = [
                    'id' => (string) $result->getKey(),
                    'score' => is_numeric($result->getAttribute('_score')) ? (float) $result->getAttribute('_score') : 1.0,
                    'source' => $result->getAttributes(),
                ];

                continue;
            }

            if (is_array($result)) {
                $id = $result['_id'] ?? $result['id'] ?? null;

                if (is_scalar($id)) {
                    $hits[] = [
                        'id' => (string) $id,
                        'score' => is_numeric($result['_score'] ?? null) ? (float) $result['_score'] : 1.0,
                        'source' => $result,
                    ];
                }
            }
        }

        $total ??= count($hits);

        return new AdvancedSearchResult(
            hits: $hits,
            total: $total,
            page: $page,
            perPage: $perPage,
            totalPages: (int) ceil($total / max(1, $perPage)),
            meta: ['strategy' => $strategy],
        );
    }

    private function constraintApplier(): ScoutSearchConstraintApplier
    {
        return $this->constraint_applier ?? app(ScoutSearchConstraintApplier::class);
    }

    /**
     * @return array<string, array{id: string, score: float, source: array<string, mixed>, rank: int}>
     */
    private function hitsById(AdvancedSearchResult $result): array
    {
        $out = [];
        $rank = 1;

        foreach ($result->hits as $hit) {
            $id = $hit['id'];
            $out[$id] = [
                'id' => $hit['id'],
                'score' => $hit['score'],
                'source' => $hit['source'],
                'rank' => $rank,
            ];
            $rank++;
        }

        return $out;
    }

    /**
     * Min-max normalize scores within a hit set to [0, 1].
     *
     * @param  array<string, array{id: string, score: float, source: array<string, mixed>, rank: int}>  $hits
     * @return array<string, array{id: string, score: float, source: array<string, mixed>, rank: int}>
     */
    private function minMaxNormalizeScores(array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $scores = array_column($hits, 'score');
        $min_score = min($scores);
        $max_score = max($scores);
        $range = $max_score - $min_score;

        foreach ($hits as &$hit) {
            $hit['score'] = $range > 0.0
                ? ($hit['score'] - $min_score) / $range
                : 1.0;
        }

        return $hits;
    }

    /**
     * Fuse multiple retrieval strategies using weighted scoring and RRF.
     *
     * @param  array<string, array<string, array{id: string, score: float, source: array<string, mixed>, rank: int}>>  $per_strategy
     * @param  array<string, float>  $weights
     * @return list<array{id: string, score: float, source: array<string, mixed>}>
     */
    private function fuseStrategies(
        array $per_strategy,
        array $weights,
        float $agreement_boost,
        int $rrf_k,
        float $rrf_weight,
    ): array {
        $normalized_strategies = [];

        foreach ($per_strategy as $name => $hits) {
            $normalized_strategies[$name] = $this->minMaxNormalizeScores($hits);
        }

        $all_ids = [];

        foreach ($normalized_strategies as $hits) {
            foreach (array_keys($hits) as $id) {
                $all_ids[$id] = true;
            }
        }

        $fused = [];

        foreach (array_keys($all_ids) as $id) {
            $weighted_score = 0.0;
            $rrf_score = 0.0;
            $appearances = 0;
            $source = [];

            foreach ($normalized_strategies as $strategy_name => $hits) {
                if (! isset($hits[$id])) {
                    continue;
                }

                $appearances++;
                $weight = $weights[$strategy_name] ?? 0.0;
                $weighted_score += $hits[$id]['score'] * $weight;
                $rrf_score += 1.0 / ($rrf_k + $hits[$id]['rank']);

                if ($source === []) {
                    $source = $hits[$id]['source'];
                }
            }

            $strategy_count = count($normalized_strategies);
            $agreement = $strategy_count > 1 && $appearances > 1
                ? $agreement_boost * ($appearances / $strategy_count)
                : 0.0;

            $final_score = $weighted_score + ($rrf_score * $rrf_weight) + $agreement;

            $fused[] = [
                'id' => (string) $id,
                'score' => $final_score,
                'source' => $source,
            ];
        }

        return $fused;
    }

    /**
     * Renormalize strategy weights so they sum to 1.0 for executed strategies only.
     *
     * @param  array<string, float>  $weights
     * @return array<string, float>
     */
    private function renormalizeWeightsForExecutedStrategies(
        array $weights,
        bool $use_fulltext,
        bool $use_vector,
    ): array {
        $active = [];

        if ($use_fulltext) {
            $active['keyword'] = $weights['keyword'] ?? 0.0;
        }

        if ($use_vector) {
            $active['vector'] = $weights['vector'] ?? 0.0;
        }

        if ($use_fulltext && $use_vector) {
            $active['hybrid'] = $weights['hybrid'] ?? 0.0;
        }

        $total = array_sum($active);

        if ($total <= 0.0) {
            return $active;
        }

        foreach ($active as $key => $value) {
            $active[$key] = $value / $total;
        }

        return $active;
    }

    /**
     * Rerank the top-K results using the injected reranker.
     *
     * @param  list<array{id: string, score: float, source: array<string, mixed>}>  $results
     * @return list<array{id: string, score: float, source: array<string, mixed>}>
     */
    private function rerankTopK(array $results, string $query, int $top_k): array
    {
        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $to_rerank = array_slice($results, 0, $top_k);
        $remaining = array_slice($results, $top_k);

        if ($to_rerank === []) {
            return $results;
        }

        $pairs = array_map(
            fn (array $item): array => [
                'query' => $query,
                'text' => $this->buildRerankerText($item['source']),
            ],
            $to_rerank,
        );

        $rerank_scores = $this->reranker->score($pairs);

        $original_max = max(array_column($to_rerank, 'score')) ?: 1.0;

        foreach ($to_rerank as $i => &$item) {
            $rerank_score = $rerank_scores[$i] ?? 0.0;
            $item['score'] = ($item['score'] * 0.4) + ($rerank_score * $original_max * 0.6);
        }

        return array_merge($to_rerank, $remaining);
    }

    /**
     * Build a text representation of a document source for reranking.
     *
     * @param  array<string, mixed>  $source
     */
    private function buildRerankerText(array $source): string
    {
        $title = $this->sourceScalarToString($source, 'title')
            ?: $this->sourceScalarToString($source, 'name');
        $body = $this->sourceScalarToString($source, 'body')
            ?: $this->sourceScalarToString($source, 'content')
            ?: $this->sourceScalarToString($source, 'description');

        return mb_trim($title . ' ' . $body);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function sourceScalarToString(array $source, string $key): string
    {
        if (! isset($source[$key]) || ! is_scalar($source[$key])) {
            return '';
        }

        return (string) $source[$key];
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
     * @param  array<string, mixed>  $section
     */
    private function planInt(array $section, string $key, int $default): int
    {
        $value = $section[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function planFloat(array $section, string $key, float $default): float
    {
        $value = $section[$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    private function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
