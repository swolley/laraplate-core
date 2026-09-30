<?php

declare(strict_types=1);

use Modules\Core\Search\Services\RankFusion;

/**
 * @param  list<array{0: string, 1: float}>  $ranking
 * @return array<string, array{id: string, score: float, raw_score: float, score_details: array<string, mixed>, source: array<string, mixed>, rank: int}>
 */
function rank_fusion_hits(array $ranking): array
{
    $hits = [];

    foreach ($ranking as $index => [$id, $score]) {
        $hits[$id] = ['id' => $id, 'score' => $score, 'raw_score' => $score, 'score_details' => [], 'source' => ['title' => $id], 'rank' => $index + 1];
    }

    return $hits;
}

it('renormalizes weights over the strategies that ran', function (): void {
    $weights = ['keyword' => 0.35, 'vector' => 0.35, 'hybrid' => 0.30];

    expect(RankFusion::renormalizeWeights($weights, useFulltext: true, useVector: false))->toBe(['keyword' => 1.0])
        ->and(RankFusion::renormalizeWeights($weights, useFulltext: true, useVector: true))->toBe($weights)
        ->and(RankFusion::renormalizeWeights(['keyword' => 0.0], useFulltext: true, useVector: false))->toBe(['keyword' => 0.0]);
});

it('min-max normalizes a strategy and maps a flat strategy to one', function (): void {
    $normalized = RankFusion::minMaxNormalize(rank_fusion_hits([['a', 10.0], ['b', 5.0], ['c', 0.0]]));
    $flat = RankFusion::minMaxNormalize(rank_fusion_hits([['a', 3.0], ['b', 3.0]]));

    expect(array_column($normalized, 'score', 'id'))->toBe(['a' => 1.0, 'b' => 0.5, 'c' => 0.0])
        ->and($normalized['b']['score_details']['normalized_score'])->toBe(0.5)
        ->and(array_column($flat, 'score', 'id'))->toBe(['a' => 1.0, 'b' => 1.0]);
});

it('fuses two strategies with weights, reciprocal rank and the agreement bonus', function (): void {
    $per_strategy = [
        'keyword' => rank_fusion_hits([['a', 10.0], ['b', 5.0]]),
        'vector' => rank_fusion_hits([['b', 0.9], ['c', 0.3]]),
    ];

    $fused = RankFusion::fuse($per_strategy, ['keyword' => 0.5, 'vector' => 0.5], agreementBoost: 0.15, rrfK: 60, rrfWeight: 0.25);
    $scores = array_column($fused, 'score', 'id');

    expect(array_column($fused, 'id'))->toBe(['a', 'b', 'c'])
        ->and($scores['a'])->toEqualWithDelta(0.5 + 0.25 / 61, 1e-12)
        ->and($scores['b'])->toEqualWithDelta(0.5 + 0.25 * (1 / 62 + 1 / 61) + 0.15, 1e-12)
        ->and($scores['c'])->toEqualWithDelta(0.25 / 62, 1e-12)
        ->and($fused[1]['score_details']['strategies'])->toHaveKeys(['keyword', 'vector']);
});

it('fuses recorded rankings with the weights renormalized as the ensemble does', function (): void {
    $keyword_only = ['keyword' => rank_fusion_hits([['a', 10.0], ['b', 5.0]])];
    $all = [
        ...$keyword_only,
        'vector' => rank_fusion_hits([['b', 0.9], ['c', 0.3]]),
        'hybrid' => rank_fusion_hits([['b', 2.0], ['a', 1.0]]),
    ];
    $weights = ['keyword' => 0.30, 'vector' => 0.40, 'hybrid' => 0.30];

    expect(RankFusion::fuseExecuted($keyword_only, $weights, 0.15, 60, 0.25))
        ->toBe(RankFusion::fuse($keyword_only, ['keyword' => 1.0], 0.15, 60, 0.25))
        ->and(RankFusion::fuseExecuted($all, $weights, 0.15, 60, 0.25))
        ->toBe(RankFusion::fuse($all, RankFusion::renormalizeWeights($weights, true, true), 0.15, 60, 0.25));
});
