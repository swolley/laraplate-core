<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ClearExpiredModelsSoftStub extends Model
{
    use SoftDeletes;

    protected $table = 'clear_expired_soft_stubs';

    protected $guarded = [];
}
