<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Modification as ApprovalModification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * @mixin \Eloquent
 * @mixin IdeHelperModification
 */
final class Modification extends ApprovalModification
{
    use HasFactory;

    #[Override]
    protected $table = CoreTables::Modifications->value;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     */
    #[Override]
    protected $hidden = [
        'modifiable_id',
        'modifiable_type',
        'modifier_id',
        'modifier_type',
        'md5',
        'active',
        'is_update',
        'approvers_required',
        'disapprovers_required',
    ];
}
