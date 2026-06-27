<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Disapproval as BaseDisapproval;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasModerationMeta;
use Override;

/**
 * @property int|null $id
 * @property int $modification_id
 * @property int $disapprover_id
 * @property string $disapprover_type
 * @property string|null $reason
 * @property array<string, mixed>|null $meta
 * @mixin \Eloquent
 * @mixin IdeHelperDisapproval
 */
final class Disapproval extends BaseDisapproval
{
    use HasModerationMeta;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Disapprovals->value;
}
