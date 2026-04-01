<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Locking\Traits\HasLocks;

final class HasGridUtilsLocksStub extends Model
{
    use HasGridUtils;
    use HasLocks;

    protected $table = 'locks_table';

    protected function casts(): array
    {
        return [];
    }
}
