<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency\Contracts;

use Modules\Core\Concurrency\BatchOutcome;
use Modules\Core\Concurrency\BatchSummary;

/**
 * Pluggable observer of a ParallelTaskRunner run.
 *
 * Implementations may render a CLI progress bar, push events to a websocket,
 * write structured logs, or do nothing at all.
 *
 * Implementations are invoked synchronously from the parent process, so they
 * must be cheap and non-blocking; long-running side effects should be
 * dispatched (e.g. via queue) and not executed inline.
 */
interface BatchReporter
{
    /**
     * Called once before the first task is scheduled.
     *
     * @param  int  $totalTasks  Number of BatchTasks the runner is about to execute
     * @param  int  $totalUnits  Sum of units across all tasks (for progress bars)
     */
    public function start(int $totalTasks, int $totalUnits): void;

    /**
     * Called once for every successfully completed task.
     */
    public function progress(BatchOutcome $outcome): void;

    /**
     * Called once for every failed task. Always invoked, regardless of policy.
     */
    public function failure(BatchOutcome $outcome): void;

    /**
     * Called exactly once after the runner has finished.
     */
    public function finish(BatchSummary $summary): void;
}
