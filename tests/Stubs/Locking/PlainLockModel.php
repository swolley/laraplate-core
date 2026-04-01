<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;

final class PlainLockModel extends Model
{
    protected $table = 'plain_lock_models';
}
