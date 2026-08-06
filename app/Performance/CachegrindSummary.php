<?php

declare(strict_types=1);

namespace Modules\Core\Performance;

/**
 * Ranked self-cost summary of a cachegrind profile.
 */
final readonly class CachegrindSummary
{
    /**
     * @param  list<FunctionCost>  $functions  Ranked most-expensive-first.
     */
    public function __construct(
        public int $totalSelf,
        public array $functions,
    ) {}
}
