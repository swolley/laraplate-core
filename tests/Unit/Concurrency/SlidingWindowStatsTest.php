<?php

declare(strict_types=1);

use Modules\Core\Concurrency\Stats\SlidingWindowStats;

it('returns zero throughput when empty', function (): void {
    $stats = new SlidingWindowStats(30);

    expect($stats->unitsPerSecond())->toBe(0.0);
    expect($stats->sampleCount())->toBe(0);
});

it('computes throughput across recorded samples', function (): void {
    $stats = new SlidingWindowStats(30);

    $stats->record(duration: 1.0, units: 100);
    $stats->record(duration: 2.0, units: 200);

    expect($stats->unitsPerSecond())->toBe(100.0);
    expect($stats->sampleCount())->toBe(2);
});

it('returns zero when total duration is zero', function (): void {
    $stats = new SlidingWindowStats(30);
    $stats->record(duration: 0.0, units: 50);

    expect($stats->unitsPerSecond())->toBe(0.0);
});

it('drops samples older than the window when new ones arrive', function (): void {
    $stats = new SlidingWindowStats(1);

    $stats->record(duration: 1.0, units: 100);

    expect($stats->sampleCount())->toBe(1);

    usleep(1_200_000);

    $stats->record(duration: 1.0, units: 200);

    expect($stats->sampleCount())->toBe(1);
    expect($stats->unitsPerSecond())->toBe(200.0);
});
