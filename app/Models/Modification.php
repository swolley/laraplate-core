<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Modification as ApprovalModification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;

/**
 * @mixin IdeHelperModification
 */
final class Modification extends ApprovalModification
{
    use HasFactory;

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
