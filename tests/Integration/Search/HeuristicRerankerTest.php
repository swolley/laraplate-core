<?php

declare(strict_types=1);

use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Services\HeuristicReranker;

it('implements IReranker contract', function (): void {
    expect(new HeuristicReranker)->toBeInstanceOf(IReranker::class);
});

it('returns empty array for empty pairs', function (): void {
    $reranker = new HeuristicReranker;
    expect($reranker->score([]))->toBe([]);
});

it('scores exact phrase match in text higher', function (): void {
    $reranker = new HeuristicReranker;
    $scores = $reranker->score([
        ['query' => 'climate change', 'text' => 'climate change is a global issue affecting many countries'],
        ['query' => 'climate change', 'text' => 'the weather today is sunny and warm'],
    ]);
    expect($scores)->toHaveCount(2);
    expect($scores[0])->toBeGreaterThan($scores[1]);
});

it('boosts exact phrase in title area (first 100 chars)', function (): void {
    $reranker = new HeuristicReranker;
    $title_match = 'climate change effects on biodiversity';
    $body_match = str_repeat('filler text ', 20) . 'climate change effects on biodiversity';

    $scores = $reranker->score([
        ['query' => 'climate change', 'text' => $title_match],
        ['query' => 'climate change', 'text' => $body_match],
    ]);
    expect($scores[0])->toBeGreaterThan($scores[1]);
});

it('scores keyword overlap proportionally', function (): void {
    $reranker = new HeuristicReranker;
    $scores = $reranker->score([
        ['query' => 'red blue green', 'text' => 'the red and blue and green colors'],
        ['query' => 'red blue green', 'text' => 'the red color only'],
    ]);
    expect($scores[0])->toBeGreaterThan($scores[1]);
});

it('returns scores clamped to 0-1 range', function (): void {
    $reranker = new HeuristicReranker;
    $scores = $reranker->score([
        ['query' => 'test', 'text' => 'test test test test test'],
        ['query' => 'nothing', 'text' => 'completely unrelated document'],
    ]);

    foreach ($scores as $score) {
        expect($score)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
    }
});

it('returns zero for empty query or text', function (): void {
    $reranker = new HeuristicReranker;
    $scores = $reranker->score([
        ['query' => '', 'text' => 'some text'],
        ['query' => 'some query', 'text' => ''],
    ]);
    expect($scores[0])->toBe(0.0);
    expect($scores[1])->toBe(0.0);
});
