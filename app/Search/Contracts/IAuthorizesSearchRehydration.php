<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A searchable model whose hits need an authorization the index cannot hold,
 * applied when search hits are turned back into models.
 */
interface IAuthorizesSearchRehydration
{
    /**
     * @param  Builder<Model>  $query  the rehydration query, already restricted to the hit ids
     * @return Builder<Model>
     */
    public function authorizeSearchRehydration(Builder $query): Builder;
}
