<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

/**
 * Self-cost of a single function within a profiling run.
 */
final readonly class FunctionCost
{
    public function __construct(
        public string $name,
        public int $self,
        public float $percent,
    ) {}
}
