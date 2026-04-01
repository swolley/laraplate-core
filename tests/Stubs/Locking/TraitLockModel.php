<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

final class TraitLockModel extends Model
{
    use HasLocks;

    protected $table = 'trait_lock_models';
}
