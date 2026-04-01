<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasApprovals;

final class HasApprovalsStubModel extends Model
{
    use HasApprovals;

    protected $table = 'has_approvals_stub';

    protected $fillable = ['name'];
}
