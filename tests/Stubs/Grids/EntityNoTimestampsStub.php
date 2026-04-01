<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class EntityNoTimestampsStub extends Model
{
    use HasGridUtils;

    public $timestamps = false;

    protected $table = 'no_timestamps';

    protected function casts(): array
    {
        return [];
    }
}
