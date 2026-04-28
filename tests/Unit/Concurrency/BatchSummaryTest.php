<?php

declare(strict_types=1);

use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Concurrency\BatchSummary;

it('reports counts and throughput', function (): void {
    $success = BatchOutcome::success('a', 100, 1.0);
    $failure = BatchOutcome::failure('b', 0, 0.5, new \RuntimeException('x'));

    $summary = new BatchSummary(
        outcomes: [$success],
        failures: [$failure],
        totalUnitsProcessed: 100,
        totalDuration: 2.0,
        totalTasks: 2,
    );

    expect($summary->hasFailures())->toBeTrue();
    expect($summary->failureCount())->toBe(1);
    expect($summary->successCount())->toBe(1);
    expect($summary->totalTasks)->toBe(2);
    expect($summary->unitsPerSecond())->toBe(50.0);
});

it('handles zero-duration runs without dividing by zero', function (): void {
    $summary = new BatchSummary(outcomes: [], failures: [], totalUnitsProcessed: 0, totalDuration: 0.0, totalTasks: 0);

    expect($summary->unitsPerSecond())->toBe(0.0);
    expect($summary->hasFailures())->toBeFalse();
});
