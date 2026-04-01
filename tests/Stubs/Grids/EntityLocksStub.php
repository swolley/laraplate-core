<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Locking\Traits\HasLocks;

final class EntityLocksStub extends Model
{
    use HasGridUtils;
    use HasLocks;

    protected $table = 'entity_locks';

    protected function casts(): array
    {
        return [];
    }
}
