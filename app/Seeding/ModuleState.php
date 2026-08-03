<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

enum ModuleState
{
    case Enabled;
    case Disabled;
    case Absent;
}
