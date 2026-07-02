<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeders;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasApprovals;

final class SeedersApprovalBulkStubModel extends Model
{
    use HasApprovals;

    protected $table = 'seeders_approval_bulk_stub';

    protected $fillable = ['name'];
}
