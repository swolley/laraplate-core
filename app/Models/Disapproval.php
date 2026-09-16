<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Disapproval as BaseDisapproval;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasModerationMeta;
use Override;

final class Disapproval extends BaseDisapproval
{
    use HasModerationMeta;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Disapprovals->value;
}
