<?php

declare(strict_types=1);

use Modules\Core\Search\Services\SearchQualityEvaluator;

/**
 * @return list<array{id: string, score: float}>
 */
function qualityHits(int $count, float $score = 2.0, ?int $distinct = null): array
{
    return array_map(
        static fn (int $i): array => ['id' => 'doc-' . ($distinct === null ? $i : $i % $distinct), 'score' => $score],
        range(1, $count),
    );
}

it('measures the average and best score, the count and the distinct ids of the results', function (): void {
    $quality = (new SearchQualityEvaluator)->evaluate([
        ['id' => 'a', 'score' => 1.0],
        ['id' => 'b', 'score' => 3.0],
        ['id' => 'a', 'score' => 2.0],
    ]);

    expect($quality)->toBe(['avg_score' => 2.0, 'max_score' => 3.0, 'count' => 3, 'unique_ids' => 2]);
});

it('measures no results as nothing', function (): void {
    expect((new SearchQualityEvaluator)->evaluate([]))->toBe(['avg_score' => 0.0, 'max_score' => 0.0, 'count' => 0, 'unique_ids' => 0]);
});

it('retries a search that found too few results, too few distinct ones, or scored low', function (array $hits): void {
    $evaluator = new SearchQualityEvaluator;

    expect($evaluator->shouldRetry($evaluator->evaluate($hits), 1, []))->toBeTrue();
})->with([
    'too few results' => [fn (): array => qualityHits(4)],
    'too few distinct results' => [fn (): array => qualityHits(10, distinct: 2)],
    'a low average score' => [fn (): array => qualityHits(10, score: 1.0)],
    'nothing' => [[]],
]);

it('does not retry a search that is good enough, or one that has no attempt left, or when the plan forbids it', function (): void {
    $evaluator = new SearchQualityEvaluator;
    $good = $evaluator->evaluate(qualityHits(10));
    $poor = $evaluator->evaluate(qualityHits(2));

    expect($evaluator->shouldRetry($good, 1, []))->toBeFalse()
        ->and($evaluator->shouldRetry($poor, 2, []))->toBeFalse()
        ->and($evaluator->shouldRetry($poor, 1, ['retry_policy' => ['enabled' => false]]))->toBeFalse()
        ->and($evaluator->shouldRetry($poor, 1, ['retry_policy' => ['max_attempts' => 1]]))->toBeFalse()
        ->and($evaluator->shouldRetry($poor, 2, ['retry_policy' => ['max_attempts' => 3]]))->toBeTrue();
});

it('takes the threshold of the average score from the plan', function (): void {
    $evaluator = new SearchQualityEvaluator;
    $quality = $evaluator->evaluate(qualityHits(10, score: 1.0));

    expect($evaluator->shouldRetry($quality, 1, ['retry_policy' => ['threshold_avg_score' => 0.5]]))->toBeFalse()
        ->and($evaluator->shouldRetry($quality, 1, ['retry_policy' => ['threshold_avg_score' => 2.0]]))->toBeTrue();
});
