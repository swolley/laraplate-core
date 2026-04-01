<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class HasGridUtilsPivotPermissionStub extends Model
{
    use HasGridUtils;

    protected $table = 'pivot_permissions';

    protected function casts(): array
    {
        return [];
    }
}
