<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

/**
 * A lockable model that keeps one attribute writable while locked.
 *
 * Stands in for a record whose lock protects what a person agreed while leaving a counter that a
 * downstream process maintains alone, the shape ERP sales order lines have.
 */
final class PartiallyWritableLockModel extends Model
{
    use HasLocks;

    public $timestamps = false;

    protected $table = 'lockable_test_models';

    protected $fillable = ['name', 'counter'];

    protected $hidden = [];

    /**
     * @return list<string>
     */
    public function attributesWritableWhileLocked(): array
    {
        return ['counter'];
    }
}
