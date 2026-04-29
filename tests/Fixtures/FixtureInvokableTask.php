<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

final class FixtureInvokableTask
{
    public function __invoke(int $a, int $b): int
    {
        return $a + $b;
    }
}