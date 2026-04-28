<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency;

use Closure;
use Illuminate\Support\Facades\Log;
use Modules\Core\Concurrency\Contracts\BatchReporter;
use Modules\Core\Concurrency\Exceptions\BatchExecutionFailedException;
use Modules\Core\Concurrency\Reporters\NullReporter;
use Modules\Core\Concurrency\Sizing\ResourceSizer;
use ReflectionObject;
use Spatie\Fork\Exceptions\CouldNotManageTask;
use Spatie\Fork\Fork;
use Throwable;

/**
 * Run a set of independent tasks in parallel through a fork-based worker pool.
 *
 * The runner wraps spatie/fork (the same library Laravel's "fork" Concurrency
 * driver uses) and adds:
 *  - a configurable concurrency cap (with optional auto-sizing on CPU and DB);
 *  - per-task reporting via the BatchReporter contract;
 *  - error policies (FailFast vs ContinueOnError);
 *  - configurable child-process hooks (e.g. DB::reconnect()).
 *
 * Tasks must be independent and side-effect-isolated. Results are passed back
 * to the parent through unix-domain sockets (≤ 64 KB per task is safe).
 *
 * Example usage:
 *
 *     $tasks = collect($models)->map(fn ($m) => new BatchTask(
 *         id: "model_{$m->id}",
 *         units: 1,
 *         run: fn () => $m->reindex(),
 *     ));
 *
 *     $summary = ParallelTaskRunner::make()
 *         ->concurrent(10)
 *         ->withResourceSizing('pgsql')
 *         ->beforeChild(fn () => DB::reconnect())
 *         ->reportTo(new ProgressBarReporter('Reindexing', $tasks->count()))
 *         ->run($tasks);
 */
final class ParallelTaskRunner
{
    private int $concurrent = 1;

    private int $batchSize = 0;

    private ?string $sizingConnection = null;

    private bool $useResourceSizing = false;

    private ErrorPolicy $errorPolicy = ErrorPolicy::FailFast;

    private BatchReporter $reporter;

    private bool $keepResults = false;

    private ?Closure $beforeChild = null;

    private ?Closure $afterChild = null;

    public function __construct()
    {
        $this->reporter = new NullReporter();
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * Set the maximum number of forks alive at any time. Default: 1 (sequential).
     */
    public function concurrent(int $n): self
    {
        $this->concurrent = max(1, $n);

        return $this;
    }

    /**
     * Enable automatic sizing based on CPU cores and database availability.
     *
     * @param  string|null  $connection  Database connection name; null skips DB cap
     * @param  int  $batchSize  Reference batch size used to recompute a proportional value
     */
    public function withResourceSizing(?string $connection = null, int $batchSize = 0): self
    {
        $this->useResourceSizing = true;
        $this->sizingConnection = $connection;
        $this->batchSize = max(0, $batchSize);

        return $this;
    }

    /**
     * Pluggable observer of run progress.
     */
    public function reportTo(BatchReporter $reporter): self
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function errorPolicy(ErrorPolicy $policy): self
    {
        $this->errorPolicy = $policy;

        return $this;
    }

    /**
     * Keep all successful BatchOutcome instances in the final BatchSummary.
     *
     * Disabled by default to keep memory bounded on huge runs; failures are
     * always kept regardless of this flag.
     */
    public function keepResults(bool $keep = true): self
    {
        $this->keepResults = $keep;

        return $this;
    }

    /**
     * Hook executed inside each forked child BEFORE its task is invoked.
     *
     * Useful for resetting framework state inherited from the parent (e.g.
     * DB::reconnect()) once per worker fork.
     */
    public function beforeChild(Closure $callback): self
    {
        $this->beforeChild = $callback;

        return $this;
    }

    /**
     * Hook executed inside each forked child AFTER its task is invoked.
     */
    public function afterChild(Closure $callback): self
    {
        $this->afterChild = $callback;

        return $this;
    }

    /**
     * Run the given tasks. Always returns a BatchSummary unless ErrorPolicy
     * is FailFast and a failure happens, in which case
     * BatchExecutionFailedException is thrown.
     *
     * @param  iterable<BatchTask>  $tasks
     */
    public function run(iterable $tasks): BatchSummary
    {
        $task_list = $this->materializeTasks($tasks);

        if ($task_list === []) {
            $summary = new BatchSummary([], [], 0, 0.0, 0);
            $this->reporter->finish($summary);

            return $summary;
        }

        $total_tasks = count($task_list);
        $total_units = array_sum(array_map(static fn (BatchTask $t): int => $t->units, $task_list));

        $effective_concurrent = $this->resolveEffectiveConcurrency();

        $this->reporter->start($total_tasks, $total_units);

        /** @var list<BatchOutcome> $outcomes */
        $outcomes = [];
        /** @var list<BatchOutcome> $failures */
        $failures = [];
        $total_units_processed = 0;
        $first_failure = null;

        $start_time = microtime(true);
        $fork = Fork::new()->concurrent($effective_concurrent);

        if ($this->beforeChild !== null) {
            $fork = $fork->before(child: $this->beforeChild);
        }

        if ($this->afterChild !== null) {
            $fork = $fork->after(child: $this->afterChild);
        }

        $fork = $fork->after(parent: function (mixed $result) use (
            &$outcomes,
            &$failures,
            &$total_units_processed,
            &$first_failure,
            $fork,
        ): void {
            if (! $result instanceof BatchOutcome) {
                return;
            }

            if ($result->success) {
                $total_units_processed += $result->unitsProcessed;

                if ($this->keepResults) {
                    $outcomes[] = $result;
                }

                $this->safeReport(fn () => $this->reporter->progress($result));

                return;
            }

            $failures[] = $result;
            $this->safeReport(fn () => $this->reporter->failure($result));

            if ($this->errorPolicy === ErrorPolicy::FailFast && $first_failure === null) {
                $first_failure = $result;
                $this->drainPendingTasks($fork);
            }
        });

        $closures = array_map(
            fn (BatchTask $task): Closure => fn (): BatchOutcome => $this->executeTask($task),
            $task_list,
        );

        try {
            $fork->run(...$closures);
        } catch (CouldNotManageTask $e) {
            Log::error('ParallelTaskRunner: a child process exited unexpectedly.', [
                'exception' => $e,
                'message' => $e->getMessage(),
            ]);

            $failures[] = new BatchOutcome(
                taskId: 'unknown_crashed_task',
                success: false,
                unitsProcessed: 0,
                duration: microtime(true) - $start_time,
                error: [
                    'message' => 'Child process crashed (possible fatal error / OOM): ' . $e->getMessage(),
                    'class' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
            );
        }

        $summary = new BatchSummary(
            outcomes: $outcomes,
            failures: $failures,
            totalUnitsProcessed: $total_units_processed,
            totalDuration: microtime(true) - $start_time,
            totalTasks: $total_tasks,
        );

        $this->safeReport(fn () => $this->reporter->finish($summary));

        if ($first_failure !== null) {
            throw new BatchExecutionFailedException($first_failure);
        }

        if ($this->errorPolicy === ErrorPolicy::FailFast && $summary->hasFailures()) {
            throw new BatchExecutionFailedException($summary->failures[0]);
        }

        return $summary;
    }

    /**
     * Convert an iterable<BatchTask> into a list (validating the type).
     *
     * @param  iterable<BatchTask>  $tasks
     * @return list<BatchTask>
     */
    private function materializeTasks(iterable $tasks): array
    {
        $list = [];

        foreach ($tasks as $task) {
            if (! $task instanceof BatchTask) {
                throw new \InvalidArgumentException(
                    'ParallelTaskRunner::run() expects iterable<BatchTask>, got ' . get_debug_type($task),
                );
            }

            $list[] = $task;
        }

        return $list;
    }

    /**
     * Resolve the final concurrency value, applying ResourceSizer when configured.
     */
    private function resolveEffectiveConcurrency(): int
    {
        if (! $this->useResourceSizing) {
            return $this->concurrent;
        }

        $sizer = ResourceSizer::compute(
            requestedParallel: $this->concurrent,
            batchSize: max(1, $this->batchSize),
            connection: $this->sizingConnection,
        );

        foreach ($sizer->warnings as $warning) {
            Log::info('ParallelTaskRunner sizing: ' . $warning);
        }

        return $sizer->effectiveParallel;
    }

    /**
     * Execute a task inside the child and convert any throwable into a
     * BatchOutcome::failure(), so the child always exits cleanly.
     */
    private function executeTask(BatchTask $task): BatchOutcome
    {
        $start = microtime(true);

        try {
            $output = $task->execute();

            return BatchOutcome::success(
                taskId: $task->id,
                units: $task->units,
                duration: microtime(true) - $start,
                output: $output,
            );
        } catch (Throwable $e) {
            return BatchOutcome::failure(
                taskId: $task->id,
                units: 0,
                duration: microtime(true) - $start,
                e: $e,
            );
        }
    }

    /**
     * Wrap reporter calls so a buggy reporter never breaks the run.
     */
    private function safeReport(Closure $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('ParallelTaskRunner: reporter threw an exception, ignoring.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * On FailFast, prevent any further tasks from being scheduled while the
     * currently running ones drain naturally. We reach into spatie/fork via
     * Reflection because Fork doesn't expose the queue publicly; this is the
     * most surgical way to stop scheduling without changing the upstream API.
     */
    private function drainPendingTasks(Fork $fork): void
    {
        try {
            $reflection = new ReflectionObject($fork);

            if ($reflection->hasProperty('queue')) {
                $property = $reflection->getProperty('queue');
                $property->setValue($fork, []);
            }
        } catch (Throwable $e) {
            Log::warning('ParallelTaskRunner: unable to drain Fork queue on fail-fast.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
