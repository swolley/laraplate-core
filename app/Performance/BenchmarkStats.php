<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

use InvalidArgumentException;

/**
 * Immutable summary statistics over a set of benchmark samples.
 *
 * Percentiles use the nearest-rank method so the reported values are always
 * real observed samples (no interpolation), which keeps latency figures honest.
 */
final readonly class BenchmarkStats
{
    public function __construct(
        public int $count,
        public float $min,
        public float $max,
        public float $mean,
        public float $p50,
        public float $p95,
        public float $p99,
    ) {}

    /**
     * @param  list<float>  $samples
     */
    public static function fromSamples(array $samples): self
    {
        if ($samples === []) {
            throw new InvalidArgumentException('Cannot compute statistics over an empty sample set.');
        }

        sort($samples);
        $count = count($samples);

        return new self(
            count: $count,
            min: $samples[0],
            max: $samples[$count - 1],
            mean: array_sum($samples) / $count,
            p50: self::nearestRank($samples, 50),
            p95: self::nearestRank($samples, 95),
            p99: self::nearestRank($samples, 99),
        );
    }

    /**
     * @param  list<float>  $sorted  Ascending-sorted samples.
     */
    private static function nearestRank(array $sorted, int $percentile): float
    {
        $count = count($sorted);
        $rank = (int) ceil($percentile / 100 * $count);
        $rank = max(1, min($rank, $count));

        return $sorted[$rank - 1];
    }
}
