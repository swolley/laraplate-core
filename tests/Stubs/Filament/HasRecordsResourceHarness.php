<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Modules\Core\Models\Setting;

final class HasRecordsResourceHarness
{
    public static function getModel(): string
    {
        return Setting::class;
    }
}
