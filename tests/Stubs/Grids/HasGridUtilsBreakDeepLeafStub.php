<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsBreakDeepLeafStub extends Model
{
    use HasGridUtils;

    protected $table = 'break_leaf';

    protected function casts(): array
    {
        return [];
    }
}
