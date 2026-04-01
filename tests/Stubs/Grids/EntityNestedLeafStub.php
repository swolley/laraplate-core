<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class EntityNestedLeafStub extends Model
{
    use HasGridUtils;

    protected $table = 'nested_leaf';

    protected function casts(): array
    {
        return [];
    }
}
