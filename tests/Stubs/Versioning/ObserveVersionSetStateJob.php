<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;

final class ObserveVersionSetStateJob implements ShouldQueue
{
    public static ?bool $sawActiveSet = null;

    public function handle(VersionSetManagerInterface $manager): void
    {
        self::$sawActiveSet = $manager->current() !== null;
    }
}
