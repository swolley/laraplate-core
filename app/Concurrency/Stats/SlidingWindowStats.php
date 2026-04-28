<?php

declare(strict_types=1);

namespace Modules\Core\Concurrency\Stats;

/**
 * Moving-average statistics over a time window.
 *
 * Used by reporters to compute live throughput (units/sec) and ETA based on
 * recent samples only, so the readout reflects current performance instead of
 * being skewed by early warm-up.
 */
final class SlidingWindowStats
{
    /**
     * @var list<array{time: float, duration: float, units: int}>
     */
    private array $entries = [];

    public function __construct(private readonly int $windowSeconds = 30) {}

    /**
     * Record a completed unit of work.
     */
    public function record(float $duration, int $units): void
    {
        $this->entries[] = [
            'time' => microtime(true),
            'duration' => $duration,
            'units' => $units,
        ];

        $this->prune();
    }

    /**
     * Return the average units per second computed on the active window.
     *
     * Returns 0.0 when no samples are available or when total duration is zero.
     */
    public function unitsPerSecond(): float
    {
        $total_duration = 0.0;
        $total_units = 0;

        foreach ($this->entries as $entry) {
            $total_duration += $entry['duration'];
            $total_units += $entry['units'];
        }

        return $total_duration > 0 ? $total_units / $total_duration : 0.0;
    }

    /**
     * Return the number of samples currently kept in the window.
     */
    public function sampleCount(): int
    {
        return count($this->entries);
    }

    /**
     * Drop samples older than the window.
     */
    private function prune(): void
    {
        $cutoff = microtime(true) - $this->windowSeconds;

        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => $entry['time'] >= $cutoff,
        ));
    }
}
