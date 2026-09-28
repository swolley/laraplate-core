<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Approvals;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\SoftDeletes\SoftDeletes;

final class SoftDeletableApprovalModel extends Model
{
    use HasApprovals;
    use SoftDeletes;

    /**
     * @var list<Operation>|null
     */
    public static ?array $operations = null;

    /**
     * Approvals required by the instances built from now on; null keeps the trait default.
     */
    public static ?int $approvers = null;

    /**
     * @var list<string>
     */
    public static array $writable = [];

    protected $table = 'approvals_soft_stub';

    protected $fillable = ['name'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (self::$approvers !== null) {
            $this->approversRequired = self::$approvers;
        }
    }

    public function approvalOperations(): array
    {
        return self::$operations ?? Operation::cases();
    }

    public function attributesWritableWhilePendingDeletion(): array
    {
        return self::$writable;
    }
}
