<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Approval as BaseApproval;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasModerationMeta;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperApproval
 */
final class Approval extends BaseApproval
{
    use HasModerationMeta;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Approvals->value;
}
