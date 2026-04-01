<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsInverseChildSourceStub extends HasGridUtilsInverseBaseSourceStub
{
    protected $table = 'inverse_child_sources';
}
