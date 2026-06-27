<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Approval as BaseApproval;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasModerationMeta;
use Override;

/**
 * @property int|null $id
 * @property int $modification_id
 * @property int $approver_id
 * @property string $approver_type
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
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
