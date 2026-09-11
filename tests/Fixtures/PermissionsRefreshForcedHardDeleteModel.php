<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\SoftDeletes\SoftDeletes;

/**
 * A model that carries the soft-delete trait and turns it off in its own code, the way
 * {@see \Modules\Core\Models\License} does. The inactivate and activate operations do
 * not exist for it, so neither do their permissions.
 */
final class PermissionsRefreshForcedHardDeleteModel extends Model
{
    use SoftDeletes;

    protected bool $softDeletesEnabled = false;

    protected $table = 'perm_refresh_forced_hard_delete';
}
