<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Unit\Concurrency;

final class InvokableSumForBatchTask
{
    public function __invoke(int $a, int $b): int
    {
        return $a + $b;
    }
}
