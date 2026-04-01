<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;

final class EntityPlainModelStub extends Model
{
    protected $table = 'plain_table';
}
