<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Concurrency\BatchSummary;
use Modules\Core\Concurrency\BatchTask;
use Modules\Core\Concurrency\Contracts\BatchReporter;
use Modules\Core\Concurrency\ErrorPolicy;
use Modules\Core\Concurrency\Exceptions\BatchExecutionFailedException;
use Modules\Core\Concurrency\ParallelTaskRunner;

beforeEach(function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl extension not available; ParallelTaskRunner needs forks.');
    }

    if (! Container::getInstance()->bound('log')) {
        Container::getInstance()->singleton('log', static fn () => new class
        {
            public function info(): void {}

            public function warning(): void {}

            public function error(): void {}

            public function debug(): void {}

            public function notice(): void {}

            public function critical(): void {}

            public function alert(): void {}

            public function emergency(): void {}

            public function log(): void {}
        });
    }
});

it('returns an empty summary when no tasks are given', function (): void {
    $summary = ParallelTaskRunner::make()->run([]);

    expect($summary)->toBeInstanceOf(BatchSummary::class);
    expect($summary->totalTasks)->toBe(0);
    expect($summary->hasFailures())->toBeFalse();
});

it('runs a single task in a child process and reports its output back to the parent', function (): void {
    $tasks = [
        new BatchTask(id: 'only', units: 7, run: fn (): int => 42),
    ];

    $summary = ParallelTaskRunner::make()
        ->concurrent(1)
        ->keepResults(true)
        ->run($tasks);

    expect($summary->totalTasks)->toBe(1);
    expect($summary->totalUnitsProcessed)->toBe(7);
    expect($summary->successCount())->toBe(1);
    expect($summary->outcomes[0]->output)->toBe(42);
});

it('runs multiple tasks with a worker pool and accumulates units', function (): void {
    $tasks = [];

    for ($i = 0; $i < 6; $i++) {
        $tasks[] = new BatchTask(
            id: "task_{$i}",
            units: 10,
            run: function (): int {
                usleep(50_000);

                return 1;
            },
        );
    }

    $summary = ParallelTaskRunner::make()
        ->concurrent(3)
        ->run($tasks);

    expect($summary->totalTasks)->toBe(6);
    expect($summary->totalUnitsProcessed)->toBe(60);
    expect($summary->hasFailures())->toBeFalse();
});

it('throws BatchExecutionFailedException on FailFast', function (): void {
    $tasks = [
        new BatchTask(id: 'ok', units: 5, run: fn (): int => 1),
        new BatchTask(id: 'boom', units: 5, run: function (): never {
            throw new \RuntimeException('intentional');
        }),
    ];

    $caught = null;

    try {
        ParallelTaskRunner::make()
            ->concurrent(1)
            ->errorPolicy(ErrorPolicy::FailFast)
            ->run($tasks);
    } catch (\Throwable $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(BatchExecutionFailedException::class);
    expect($caught->outcome->taskId)->toBe('boom');
    expect($caught->outcome->error['message'] ?? null)->toBe('intentional');
});

it('continues on failures and collects them when policy is ContinueOnError', function (): void {
    $tasks = [
        new BatchTask(id: 'ok', units: 5, run: fn (): int => 1),
        new BatchTask(id: 'fail', units: 5, run: function (): never {
            throw new \RuntimeException('intentional');
        }),
        new BatchTask(id: 'ok2', units: 5, run: fn (): int => 1),
    ];

    $summary = ParallelTaskRunner::make()
        ->concurrent(1)
        ->errorPolicy(ErrorPolicy::ContinueOnError)
        ->run($tasks);

    expect($summary->totalTasks)->toBe(3);
    expect($summary->successCount())->toBe(2);
    expect($summary->failureCount())->toBe(1);
    expect($summary->failures[0]->taskId)->toBe('fail');
    expect($summary->totalUnitsProcessed)->toBe(10);
});

it('rejects items that are not BatchTask instances', function (): void {
    expect(fn () => ParallelTaskRunner::make()->run(['not a task']))
        ->toThrow(InvalidArgumentException::class);
});

it('invokes the reporter for start, progress and finish', function (): void {
    $reporter = new class implements BatchReporter
    {
        /** @var list<array{event: string, payload: mixed}> */
        public array $events = [];

        public function start(int $totalTasks, int $totalUnits): void
        {
            $this->events[] = ['event' => 'start', 'payload' => ['tasks' => $totalTasks, 'units' => $totalUnits]];
        }

        public function progress(BatchOutcome $outcome): void
        {
            $this->events[] = ['event' => 'progress', 'payload' => $outcome->taskId];
        }

        public function failure(BatchOutcome $outcome): void
        {
            $this->events[] = ['event' => 'failure', 'payload' => $outcome->taskId];
        }

        public function finish(BatchSummary $summary): void
        {
            $this->events[] = ['event' => 'finish', 'payload' => $summary->totalTasks];
        }
    };

    ParallelTaskRunner::make()
        ->concurrent(1)
        ->reportTo($reporter)
        ->run([
            new BatchTask(id: 'a', units: 1, run: fn (): int => 1),
            new BatchTask(id: 'b', units: 1, run: fn (): int => 2),
        ]);

    $event_names = array_column($reporter->events, 'event');

    expect($event_names[0])->toBe('start');
    expect(end($event_names))->toBe('finish');
    expect(array_count_values($event_names)['progress'] ?? 0)->toBe(2);
});
