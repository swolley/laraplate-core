<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot as BasePivot;
use Modules\Core\Models\Concerns\HasPrefixedTableName;

abstract class Pivot extends BasePivot
{
    use HasFactory;
    use HasPrefixedTableName;
}
