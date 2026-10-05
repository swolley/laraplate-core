<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * An engine that can tell the dimension of the vectors its index for a model holds. An engine without
 * this capability is simply not checked for a dimension mismatch.
 */
interface IReportsVectorDimensions
{
    /**
     * The indexed vector dimension, or null when the index or the vector field does not exist yet.
     */
    public function indexedVectorDimensions(Model $model): ?int;
}
