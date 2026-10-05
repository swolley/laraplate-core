<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\DTOs\VectorAvailability;

/**
 * Guards vector retrieval: a search asks it before embedding the query and falls back to keywords
 * when it answers no. Other modules may decorate the default binding.
 */
interface IVectorSearchAvailability
{
    public function check(Model $model): VectorAvailability;
}
