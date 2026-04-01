<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

final class ClearExpiredModelsNoSoftStub extends Model
{
    protected $table = 'clear_expired_no_soft_stubs';
}
