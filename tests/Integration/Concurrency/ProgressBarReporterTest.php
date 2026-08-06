<?php

declare(strict_types=1);

use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Concurrency\BatchSummary;
use Modules\Core\Concurrency\Reporters\ProgressBarReporter;

it('keeps the original label at the start of the finish message', function (): void {
    Sleep::fake();
    Prompt::fake();

    $reporter = new ProgressBarReporter(label: 'Creating cms_contents (parallel)');
    $reporter->start(totalTasks: 2, totalUnits: 100);

    $reporter->finish(new BatchSummary(
        outcomes: [],
        failures: [],
        totalUnitsProcessed: 100,
        totalDuration: 1.25,
        totalTasks: 2,
    ));

    $progress = (new ReflectionProperty($reporter, 'progress'))->getValue($reporter);

    expect($progress->label)
        ->toStartWith('Creating cms_contents (parallel)')
        ->toContain('Successfully processed 100 units across 2 tasks in 1.25s');
});

it('does not rewrite the label when the summary has failures', function (): void {
    Sleep::fake();
    Prompt::fake();

    $reporter = new ProgressBarReporter(label: 'Creating cms_contents (parallel)');
    $reporter->start(totalTasks: 1, totalUnits: 50);

    $reporter->finish(new BatchSummary(
        outcomes: [],
        failures: [
            BatchOutcome::failure(
                taskId: 'batch_0',
                units: 0,
                duration: 0.1,
                e: new RuntimeException('boom'),
            ),
        ],
        totalUnitsProcessed: 0,
        totalDuration: 0.1,
        totalTasks: 1,
    ));

    $progress = (new ReflectionProperty($reporter, 'progress'))->getValue($reporter);

    expect($progress->label)->toBe('Creating cms_contents (parallel)');
});
