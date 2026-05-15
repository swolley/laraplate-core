<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Approval as BaseApproval;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperApproval
 */
final class Approval extends BaseApproval
{
    #[Override]
    protected $table = CoreTables::Approvals->value;
}
