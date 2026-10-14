<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Attributes\FilterScope;

/**
 * A trait declaring a filter scope, so the guard is shown to read declarations through the traits a model uses.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait ScopeFilterStubConcern
{
    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function named(Builder $query, string $name): void
    {
        $query->where($this->qualifyColumn('name'), $name);
    }
}
