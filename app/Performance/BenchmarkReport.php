<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

/**
 * Result of running an operation through the {@see BenchmarkRunner}.
 */
final readonly class BenchmarkReport
{
    public function __construct(
        public int $iterations,
        public int $warmup,
        public BenchmarkStats $durationStats,
        public BenchmarkStats $queryStats,
        public int $peakMemoryBytes,
    ) {}
}
