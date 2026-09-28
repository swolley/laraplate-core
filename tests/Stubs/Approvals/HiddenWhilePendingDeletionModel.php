<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Approvals;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Approvals\PendingDeletionStrategy;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\SoftDeletes\SoftDeletes;

/**
 * Same table as SoftDeletableApprovalModel, hidden instead of blocked while its deletion waits.
 */
final class HiddenWhilePendingDeletionModel extends Model
{
    use HasApprovals;
    use SoftDeletes;

    protected $table = 'approvals_soft_stub';

    protected $fillable = ['name'];

    public function pendingDeletionStrategy(): PendingDeletionStrategy
    {
        return PendingDeletionStrategy::Hide;
    }
}
