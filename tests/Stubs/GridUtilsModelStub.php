<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

/**
 * Minimal model with HasGridUtils for Grid constructor tests.
 */
final class GridUtilsModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'grid_utils_model_stubs';
}
