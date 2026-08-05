<?php

declare(strict_types=1);

use Modules\Core\Performance\BenchmarkStats;

it('computes count, min, max and mean from samples', function (): void {
    $stats = BenchmarkStats::fromSamples([10.0, 20.0, 30.0, 40.0]);

    expect($stats->count)->toBe(4)
        ->and($stats->min)->toBe(10.0)
        ->and($stats->max)->toBe(40.0)
        ->and($stats->mean)->toBe(25.0);
});

it('computes nearest-rank percentiles', function (): void {
    $stats = BenchmarkStats::fromSamples([1.0, 2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0, 10.0]);

    // nearest-rank: p50 -> ceil(0.50*10)=5th value, p95 -> ceil(0.95*10)=10th value
    expect($stats->p50)->toBe(5.0)
        ->and($stats->p95)->toBe(10.0);
});

it('is order-independent (sorts internally)', function (): void {
    $stats = BenchmarkStats::fromSamples([30.0, 10.0, 40.0, 20.0]);

    expect($stats->min)->toBe(10.0)
        ->and($stats->max)->toBe(40.0)
        ->and($stats->p50)->toBe(20.0);
});

it('handles a single sample', function (): void {
    $stats = BenchmarkStats::fromSamples([7.5]);

    expect($stats->count)->toBe(1)
        ->and($stats->min)->toBe(7.5)
        ->and($stats->max)->toBe(7.5)
        ->and($stats->mean)->toBe(7.5)
        ->and($stats->p50)->toBe(7.5)
        ->and($stats->p95)->toBe(7.5);
});

it('rejects an empty sample set', function (): void {
    expect(fn (): BenchmarkStats => BenchmarkStats::fromSamples([]))
        ->toThrow(InvalidArgumentException::class);
});
