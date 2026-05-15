<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Disapproval as BaseDisapproval;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperDisapproval
 */
final class Disapproval extends BaseDisapproval
{
    #[Override]
    protected $table = CoreTables::Disapprovals->value;
}
