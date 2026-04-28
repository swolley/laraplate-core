<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency;

/**
 * Aggregated result of a ParallelTaskRunner run.
 */
final readonly class BatchSummary
{
    /**
     * @param  list<BatchOutcome>  $outcomes  Successful outcomes (may be empty when keepResults() is disabled)
     * @param  list<BatchOutcome>  $failures  Always populated when at least one task failed
     */
    public function __construct(
        public array $outcomes,
        public array $failures,
        public int $totalUnitsProcessed,
        public float $totalDuration,
        public int $totalTasks,
    ) {}

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    public function failureCount(): int
    {
        return count($this->failures);
    }

    public function successCount(): int
    {
        return $this->totalTasks - $this->failureCount();
    }

    public function unitsPerSecond(): float
    {
        return $this->totalDuration > 0 ? $this->totalUnitsProcessed / $this->totalDuration : 0.0;
    }
}
