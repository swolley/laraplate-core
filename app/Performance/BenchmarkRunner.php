<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use InvalidArgumentException;

/**
 * Runs an operation repeatedly and reports latency and query-count statistics.
 *
 * A warmup phase runs the operation without measuring so that lazy singletons,
 * autoloading and query caches settle before the measured iterations, keeping
 * the reported percentiles representative of steady-state cost.
 */
final class BenchmarkRunner
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * @param  callable():mixed  $operation
     */
    public function run(callable $operation, int $iterations = 30, int $warmup = 3): BenchmarkReport
    {
        if ($iterations < 1) {
            throw new InvalidArgumentException('Benchmark requires at least one measured iteration.');
        }

        if ($warmup < 0) {
            throw new InvalidArgumentException('Warmup iterations cannot be negative.');
        }

        $query_count = 0;
        $this->db->listen(static function (QueryExecuted $query) use (&$query_count): void {
            $query_count++;
        });

        for ($i = 0; $i < $warmup; $i++) {
            $operation();
        }

        $durations = [];
        $queries = [];

        for ($i = 0; $i < $iterations; $i++) {
            $query_count = 0;
            $start = hrtime(true);
            $operation();
            $durations[] = (hrtime(true) - $start) / 1_000_000.0;
            $queries[] = (float) $query_count;
        }

        return new BenchmarkReport(
            iterations: $iterations,
            warmup: $warmup,
            durationStats: BenchmarkStats::fromSamples($durations),
            queryStats: BenchmarkStats::fromSamples($queries),
            peakMemoryBytes: memory_get_peak_usage(true),
        );
    }
}
