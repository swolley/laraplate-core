<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;

class EntityPlainNonFinalModelStub extends Model
{
    protected $table = 'plain_non_final_table';
}
