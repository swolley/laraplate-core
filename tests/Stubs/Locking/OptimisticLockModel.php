<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasOptimisticLocking;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $note
 * @property int|null $lock_version
 */
final class OptimisticLockModel extends Model
{
    use HasOptimisticLocking;

    public $timestamps = false;

    protected $table = 'optimistic_lock_models';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'note'];
}
