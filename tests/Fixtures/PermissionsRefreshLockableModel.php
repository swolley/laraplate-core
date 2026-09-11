<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

/**
 * A model that can be locked, for the `lock`/`unlock` branch of the permission refresh.
 */
final class PermissionsRefreshLockableModel extends Model
{
    use HasLocks;

    protected $table = 'perm_refresh_lockable';
}
