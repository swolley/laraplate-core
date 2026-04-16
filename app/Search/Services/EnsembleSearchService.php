<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Services\ElasticsearchService;
use Throwable;

/**
 * Ensemble search service that combines keyword, vector and hybrid retrieval
 * strategies with Reciprocal Rank Fusion (RRF) and optional reranking.
 */
class EnsembleSearchService
{
    public function __construct(private IReranker $reranker) {}

    /**
     * Execute an ensemble search across multiple retrieval strategies.
     *
     * @param  array{keywords: list<string>, date_range: ?array<string, string>, query: array{expanded: string}}  $intent
     * @param  list<float>|null  $vector
     * @param  array<string, mixed>  $plan
     * @return array{results: list<array{id: string, score: float, source: array<string, mixed>}>, meta: array<string, mixed>}
     */
    public function search(array $intent, ?array $vector, string $query, array $plan, string $index): array
    {
        $retrieval = $plan['retrieval'] ?? [];
        $ensemble_config = $plan['ensemble'] ?? [];
        $ranking = $plan['ranking'] ?? [];

        $use_fulltext = (bool) ($retrieval['use_fulltext'] ?? true);
        $use_vector = (bool) ($retrieval['use_vector'] ?? false) && $vector !== null;
        $size = (int) ($retrieval['size'] ?? 50);

        $weights = [
            'keyword' => (float) ($ensemble_config['keyword_weight'] ?? 0.35),
            'vector' => (float) ($ensemble_config['vector_weight'] ?? 0.35),
            'hybrid' => (float) ($ensemble_config['hybrid_weight'] ?? 0.30),
        ];

        $agreement_boost = (float) ($ensemble_config['agreement_boost'] ?? 0.15);
        $rrf_k = (int) ($ensemble_config['rrf_k'] ?? 60);
        $rrf_weight = (float) ($ensemble_config['rrf_weight'] ?? 0.25);

        $per_strategy = [];

        if ($use_fulltext) {
            $keyword_body = $this->buildKeywordOnlyBody($intent, $size);
            $raw = $this->executeEsSearch($index, $keyword_body);
            $per_strategy['keyword'] = $this->normalizeHits($raw);
        }

        if ($use_vector && $vector !== null) {
            $vector_body = $this->buildVectorOnlyBody($vector, $size);
            $raw = $this->executeEsSearch($index, $vector_body);
            $per_strategy['vector'] = $this->normalizeHits($raw);
        }

        if ($use_fulltext && $use_vector && $vector !== null) {
            $hybrid_body = $this->buildHybridBody($intent, $vector, $size);
            $raw = $this->executeEsSearch($index, $hybrid_body);
            $per_strategy['hybrid'] = $this->normalizeHits($raw);
        }

        if ($per_strategy === []) {
            return ['results' => [], 'meta' => ['strategies_executed' => 0]];
        }

        $adjusted_weights = $this->renormalizeWeightsForExecutedStrategies(
            $weights,
            $use_fulltext,
            $use_vector,
        );

        $fused = $this->fuseStrategies($per_strategy, $adjusted_weights, $agreement_boost, $rrf_k, $rrf_weight);

        $use_reranker = (bool) ($ranking['use_reranker'] ?? config('search.features.reranker', false));
        $rerank_top_k = (int) ($ranking['rerank_top_k'] ?? config('search.reranker.top_k', 30));

        if ($use_reranker && $fused !== []) {
            $fused = $this->rerankTopK($fused, $query, $rerank_top_k);
        }

        $results = collect($fused)
            ->sortByDesc('score')
            ->values()
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'score' => round((float) $item['score'], 6),
                'source' => $item['source'] ?? [],
            ])
            ->all();

        return [
            'results' => $results,
            'meta' => [
                'strategies_executed' => count($per_strategy),
                'strategies' => array_keys($per_strategy),
                'reranked' => $use_reranker,
                'total_results' => count($results),
            ],
        ];
    }

    /**
     * Execute an Elasticsearch search and return the raw array response.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function executeEsSearch(string $index, array $body): array
    {
        try {
            $response = ElasticsearchService::getInstance()->client->search([
                'index' => $index,
                'body' => $body,
            ]);

            return $response->asArray();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Extract and normalize hits from an Elasticsearch response array.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, array{id: string, score: float, source: array<string, mixed>, rank: int}>
     */
    private function normalizeHits(array $response): array
    {
        $hits = $response['hits']['hits'] ?? [];

        if (! is_array($hits)) {
            return [];
        }

        $out = [];
        $rank = 1;

        foreach ($hits as $hit) {
            if (! is_array($hit) || ! isset($hit['_id'])) {
                continue;
            }

            $id = (string) $hit['_id'];
            $out[$id] = [
                'id' => $id,
                'score' => (float) ($hit['_score'] ?? 0.0),
                'source' => is_array($hit['_source'] ?? null) ? $hit['_source'] : [],
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

        foreach ($hits as $id => &$hit) {
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
        $title = (string) ($source['title'] ?? $source['name'] ?? '');
        $body = (string) ($source['body'] ?? $source['content'] ?? $source['description'] ?? '');

        return mb_trim($title . ' ' . $body);
    }

    /**
     * Build an Elasticsearch keyword-only (full-text) query body.
     *
     * @param  array{keywords: list<string>, date_range: ?array<string, string>, query: array{expanded: string}}  $intent
     * @return array<string, mixed>
     */
    private function buildKeywordOnlyBody(array $intent, int $size): array
    {
        $expanded_query = $intent['query']['expanded'] ?? '';
        $keywords = $intent['keywords'] ?? [];

        $must = [];

        if ($expanded_query !== '') {
            $must[] = [
                'multi_match' => [
                    'query' => $expanded_query,
                    'fields' => ['title^3', 'body', 'content', 'description', 'name^2'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        $should = [];

        foreach ($keywords as $keyword) {
            $should[] = [
                'match_phrase' => [
                    'title' => [
                        'query' => $keyword,
                        'boost' => 2.0,
                    ],
                ],
            ];
        }

        $body = [
            'size' => $size,
            'query' => [
                'bool' => [
                    'must' => $must !== [] ? $must : [['match_all' => (object) []]],
                    'should' => $should,
                ],
            ],
        ];

        $date_filter = $this->buildDateFilter($intent['date_range'] ?? null);

        if ($date_filter !== null) {
            $body['query']['bool']['filter'] = [$date_filter];
        }

        return $body;
    }

    /**
     * Build an Elasticsearch vector-only (kNN) query body.
     *
     * @param  list<float>  $vector
     * @return array<string, mixed>
     */
    private function buildVectorOnlyBody(array $vector, int $size): array
    {
        return [
            'size' => $size,
            'knn' => [
                'field' => 'embedding',
                'query_vector' => $vector,
                'k' => $size,
                'num_candidates' => $size * 2,
            ],
        ];
    }

    /**
     * Build an Elasticsearch hybrid (keyword + kNN) query body.
     *
     * @param  array{keywords: list<string>, date_range: ?array<string, string>, query: array{expanded: string}}  $intent
     * @param  list<float>  $vector
     * @return array<string, mixed>
     */
    private function buildHybridBody(array $intent, array $vector, int $size): array
    {
        $expanded_query = $intent['query']['expanded'] ?? '';

        $body = [
            'size' => $size,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => $expanded_query,
                                'fields' => ['title^3', 'body', 'content', 'description', 'name^2'],
                                'type' => 'best_fields',
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                    ],
                ],
            ],
            'knn' => [
                'field' => 'embedding',
                'query_vector' => $vector,
                'k' => $size,
                'num_candidates' => $size * 2,
            ],
        ];

        $date_filter = $this->buildDateFilter($intent['date_range'] ?? null);

        if ($date_filter !== null) {
            $body['query']['bool']['filter'] = [$date_filter];
        }

        return $body;
    }

    /**
     * Build an Elasticsearch date range filter clause.
     *
     * @param  array<string, string>|null  $date_range
     * @return array<string, mixed>|null
     */
    private function buildDateFilter(?array $date_range): ?array
    {
        if ($date_range === null) {
            return null;
        }

        $range = [];

        if (isset($date_range['from'])) {
            $range['gte'] = $date_range['from'];
        }

        if (isset($date_range['to'])) {
            $range['lte'] = $date_range['to'];
        }

        if ($range === []) {
            return null;
        }

        return ['range' => ['published_at' => $range]];
    }
}
