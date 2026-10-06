<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

/**
 * Judges whether the results of a search are good enough, so that a retry means something instead of
 * being the same query again. Salvaged, with its behaviour unchanged, from the retired
 * `IntelligentSearchAction`.
 *
 * A search is poor when it found fewer than five results, fewer than three distinct ones, or scored below
 * the threshold of the plan on average; the plan's `retry_policy` says whether a retry is allowed at all,
 * how many attempts the search has and which average score is too low.
 */
final readonly class SearchQualityEvaluator
{
    public const int MIN_RESULTS = 5;

    public const int MIN_UNIQUE_RESULTS = 3;

    private const int DEFAULT_MAX_ATTEMPTS = 2;

    private const float DEFAULT_THRESHOLD_AVG_SCORE = 1.5;

    /**
     * @param  list<array{id: string, score: float}>  $results  hits as the search returns them (other keys are ignored)
     * @return array{avg_score: float, max_score: float, count: int, unique_ids: int}
     */
    public function evaluate(array $results): array
    {
        $scores = array_column($results, 'score');

        return [
            'avg_score' => $scores !== [] ? array_sum($scores) / count($scores) : 0.0,
            'max_score' => $scores !== [] ? max($scores) : 0.0,
            'count' => count($results),
            'unique_ids' => count(array_unique(array_column($results, 'id'))),
        ];
    }

    /**
     * @param  array{avg_score: float, max_score: float, count: int, unique_ids: int}  $quality
     * @param  array<string, mixed>  $plan
     */
    public function shouldRetry(array $quality, int $attempt, array $plan): bool
    {
        $policy = is_array($plan['retry_policy'] ?? null) ? $plan['retry_policy'] : [];

        if (($policy['enabled'] ?? true) !== true) {
            return false;
        }

        $max_attempts = is_numeric($policy['max_attempts'] ?? null) ? (int) $policy['max_attempts'] : self::DEFAULT_MAX_ATTEMPTS;
        $threshold = is_numeric($policy['threshold_avg_score'] ?? null) ? (float) $policy['threshold_avg_score'] : self::DEFAULT_THRESHOLD_AVG_SCORE;

        return $attempt < $max_attempts
            && ($quality['count'] < self::MIN_RESULTS || $quality['avg_score'] < $threshold || $quality['unique_ids'] < self::MIN_UNIQUE_RESULTS);
    }
}
