<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Grids\Traits\HasGridUtils;

final class EntitySoftDeleteForceStub extends Model
{
    use HasGridUtils;
    use SoftDeletes;

    protected $table = 'soft_delete_force';

    protected function casts(): array
    {
        return [];
    }
}
