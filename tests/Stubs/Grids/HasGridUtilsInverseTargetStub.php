<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsInverseTargetStub extends Model
{
    use HasGridUtils;

    protected $table = 'inverse_target';

    protected function casts(): array
    {
        return [];
    }
}
