<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Collects framework boot-time samples (in milliseconds).
 */
interface BootSampler
{
    /**
     * @return list<float> Boot times in milliseconds, one per successful sample.
     */
    public function sample(int $runs): array;
}
