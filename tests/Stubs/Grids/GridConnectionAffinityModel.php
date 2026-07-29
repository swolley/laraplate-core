<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class GridConnectionAffinityModel extends Model
{
    use HasGridUtils;

    protected $connection = 'grid_layout_affinity';
}
