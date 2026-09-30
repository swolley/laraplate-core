<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

/**
 * Pure rank-fusion math shared by {@see EnsembleSearchService} and the offline retrieval tuner.
 *
 * Per strategy, scores are min-max normalized; each document then scores the weighted sum of its
 * normalized strategy scores, plus `rrf_weight` times its reciprocal-rank sum, plus an agreement
 * bonus proportional to the share of strategies that returned it. No model, engine or container:
 * recorded per-strategy rankings can be re-fused with other parameters without re-querying.
 *
 * @phpstan-type StrategyHit array{id: string, score: float, raw_score?: float|null, score_details?: array<string, mixed>, source: array<string, mixed>, rank: int}
 * @phpstan-type FusedHit array{id: string, score: float, raw_score: float|null, score_details: array<string, mixed>, source: array<string, mixed>}
 */
final class RankFusion
{
    /**
     * Renormalize strategy weights so they sum to 1.0 for executed strategies only.
     *
     * @param  array<string, float>  $weights
     * @return array<string, float>
     */
    public static function renormalizeWeights(array $weights, bool $useFulltext, bool $useVector): array
    {
        $active = [];

        if ($useFulltext) {
            $active['keyword'] = $weights['keyword'] ?? 0.0;
        }

        if ($useVector) {
            $active['vector'] = $weights['vector'] ?? 0.0;
        }

        if ($useFulltext && $useVector) {
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
     * Min-max normalize scores within a hit set to [0, 1].
     *
     * @param  array<string, StrategyHit>  $hits
     * @return array<string, StrategyHit>
     */
    public static function minMaxNormalize(array $hits): array
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
            $hit['score_details']['normalized_score'] = $hit['score'];
        }

        return $hits;
    }

    /**
     * Fuse multiple retrieval strategies using weighted scoring and RRF.
     *
     * @param  array<string, array<string, StrategyHit>>  $perStrategy
     * @param  array<string, float>  $weights
     * @return list<FusedHit>
     */
    public static function fuse(
        array $perStrategy,
        array $weights,
        float $agreementBoost,
        int $rrfK,
        float $rrfWeight,
    ): array {
        $normalized_strategies = [];

        foreach ($perStrategy as $name => $hits) {
            $normalized_strategies[$name] = self::minMaxNormalize($hits);
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
            $raw_score = null;
            $score_details = [
                'strategies' => [],
            ];

            foreach ($normalized_strategies as $strategy_name => $hits) {
                if (! isset($hits[$id])) {
                    continue;
                }

                $appearances++;
                $weight = $weights[$strategy_name] ?? 0.0;
                $weighted_score += $hits[$id]['score'] * $weight;
                $rrf_score += 1.0 / ($rrfK + $hits[$id]['rank']);

                if ($source === []) {
                    $source = $hits[$id]['source'];
                }

                if ($raw_score === null) {
                    $raw_score = $hits[$id]['raw_score'] ?? null;
                }

                $score_details['strategies'][$strategy_name] = $hits[$id]['score_details'] ?? [];
            }

            $strategy_count = count($normalized_strategies);
            $agreement = $strategy_count > 1 && $appearances > 1
                ? $agreementBoost * ($appearances / $strategy_count)
                : 0.0;

            $final_score = $weighted_score + ($rrf_score * $rrfWeight) + $agreement;

            $fused[] = [
                'id' => (string) $id,
                'score' => $final_score,
                'raw_score' => $raw_score,
                'score_details' => array_merge(
                    $appearances === 1 && count($score_details['strategies']) === 1
                        ? reset($score_details['strategies'])
                        : [],
                    [
                        'normalized_score' => $final_score,
                        'strategies' => $score_details['strategies'],
                    ],
                ),
                'source' => $source,
            ];
        }

        return $fused;
    }

    /**
     * Fuse a recorded `meta['per_strategy']` map exactly as {@see EnsembleSearchService} does:
     * keyword ran when full-text was used, vector when a vector was used, so the weights are
     * renormalized from the strategies present before fusing.
     *
     * @param  array<string, array<string, StrategyHit>>  $perStrategy
     * @param  array<string, float>  $weights  Raw `keyword`/`vector`/`hybrid` weights.
     * @return list<FusedHit>
     */
    public static function fuseExecuted(
        array $perStrategy,
        array $weights,
        float $agreementBoost,
        int $rrfK,
        float $rrfWeight,
    ): array {
        $adjusted = self::renormalizeWeights(
            $weights,
            array_key_exists('keyword', $perStrategy),
            array_key_exists('vector', $perStrategy),
        );

        return self::fuse($perStrategy, $adjusted, $agreementBoost, $rrfK, $rrfWeight);
    }
}
