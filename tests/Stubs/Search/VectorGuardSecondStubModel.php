<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * A second model class sharing the injected engine of {@see VectorGuardStubModel}, so the guard's
 * per-class cache can be checked across several classes.
 */
final class VectorGuardSecondStubModel extends Model
{
    public function searchableUsing(): ?object
    {
        return VectorGuardStubModel::$engine;
    }
}
