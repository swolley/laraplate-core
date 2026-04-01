<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

final class LockableTestModel extends Model
{
    use HasLocks;

    public $timestamps = false;

    protected $table = 'lockable_test_models';

    protected $guarded = [];

    protected $hidden = [];
}
