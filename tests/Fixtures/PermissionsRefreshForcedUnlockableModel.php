<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

/**
 * A lockable model that pins the answer in its own code: the columns are there, the
 * feature is not, so the verbs are not part of its vocabulary either.
 */
final class PermissionsRefreshForcedUnlockableModel extends Model
{
    use HasLocks;

    protected bool $locksEnabled = false;

    protected $table = 'perm_refresh_forced_unlockable';
}
