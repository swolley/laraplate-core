<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

/**
 * A lockable model that declares no attribute writable while locked.
 *
 * The platform default, kept as its own stub so the contrast with
 * {@see PartiallyWritableLockModel} is a difference of one declaration and nothing else.
 */
final class StrictlyLockedTestModel extends Model
{
    use HasLocks;

    public $timestamps = false;

    protected $table = 'lockable_test_models';

    protected $fillable = ['name', 'counter'];

    protected $hidden = [];
}
