<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Attributes\FilterScope;

/**
 * A model whose declared scope adds its condition only for a non-null parameter (the ordinary `when()` idiom), so the
 * guard must refuse the run that adds no condition instead of letting the node match every row.
 */
class ConditionalScopeFilterStubModel extends Model
{
    protected $table = 'scope_filter_stub';

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function ofName(Builder $query, ?string $name = null): void
    {
        $query->when($name !== null, fn (Builder $scoped): Builder => $scoped->where($this->qualifyColumn('name'), $name));
    }
}
