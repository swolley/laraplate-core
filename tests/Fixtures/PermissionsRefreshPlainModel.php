<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal model without SoftDeletes, RequiresApproval, or HasValidity for permission refresh edge cases.
 */
final class PermissionsRefreshPlainModel extends Model
{
    protected $table = 'perm_refresh_plain';
}
