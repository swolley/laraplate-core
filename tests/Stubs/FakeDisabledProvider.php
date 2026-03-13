<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

class FakeDisabledProvider extends FakeEnabledProvider
{
    public function isEnabled(): bool
    {
        return false;
    }
}

