<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Grids\Hooks\HasReadHooks;
use Modules\Core\Grids\Hooks\HasWriteHooks;

final class HooksHarness
{
    use HasReadHooks;
    use HasWriteHooks;
}
