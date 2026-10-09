<?php

declare(strict_types=1);

use Modules\Core\Search\Enums\SearchMode;
use Modules\Core\Search\Services\CoreSearchStrategyResolver;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SimpleQueryIntentParser;

it('answers a fast request with the cheap components and no reason', function (): void {
    $strategy = app(CoreSearchStrategyResolver::class)->resolve(SearchMode::Fast);

    expect($strategy->applied_mode)->toBe(SearchMode::Fast)
        ->and($strategy->planner)->toBeInstanceOf(FallbackSearchPlanner::class)
        ->and($strategy->reranker)->toBeInstanceOf(HeuristicReranker::class)
        ->and($strategy->intent_parser)->toBeInstanceOf(SimpleQueryIntentParser::class)
        ->and($strategy->embedder)->toBeNull()
        ->and($strategy->max_retries)->toBe(0)
        ->and($strategy->degraded_reason)->toBeNull();
});

it('serves a deep request as fast and says the mode is unavailable', function (): void {
    $strategy = app(CoreSearchStrategyResolver::class)->resolve(SearchMode::Deep);

    expect($strategy->applied_mode)->toBe(SearchMode::Fast)
        ->and($strategy->planner)->toBeInstanceOf(FallbackSearchPlanner::class)
        ->and($strategy->embedder)->toBeNull()
        ->and($strategy->max_retries)->toBe(0)
        ->and($strategy->degraded_reason)->toBe('mode_unavailable');
});

it('reads an unknown mode as fast at the boundary', function (): void {
    expect(SearchMode::tryFrom('turbo') ?? SearchMode::Fast)->toBe(SearchMode::Fast)
        ->and(SearchMode::tryFrom('deep'))->toBe(SearchMode::Deep);
});

it('serves a balanced request as fast because Core has no embedder', function (): void {
    $strategy = app(CoreSearchStrategyResolver::class)->resolve(SearchMode::Balanced);

    expect($strategy->applied_mode)->toBe(SearchMode::Fast)
        ->and($strategy->embedder)->toBeNull()
        ->and($strategy->degraded_reason)->toBe('mode_unavailable');
});
