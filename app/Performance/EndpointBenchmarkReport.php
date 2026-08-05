<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

/**
 * Benchmark of a single HTTP endpoint, pairing the raw {@see BenchmarkReport}
 * with the request coordinates and the last observed response status.
 */
final readonly class EndpointBenchmarkReport
{
    public function __construct(
        public string $method,
        public string $uri,
        public int $lastStatus,
        public BenchmarkReport $benchmark,
    ) {}
}
