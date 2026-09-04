<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Locking\Traits\HasOptimisticLocking;

/**
 * Carries both locking traits and real timestamps, so a test can prove that taking, extending and
 * releasing a lock leaves `lock_version` and `updated_at` alone.
 */
final class VersionedLockableTestModel extends Model
{
    use HasLocks;
    use HasOptimisticLocking;

    protected $table = 'versioned_lockable_test_models';

    protected $guarded = [];

    protected $hidden = [];
}
