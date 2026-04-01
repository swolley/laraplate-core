<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Definitions\HasValidations;

final class HasValidationsHarness
{
    use HasValidations;
}
