<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Attributes\FilterScope;

/**
 * A model whose declared scopes do more than add where conditions, so the guard must refuse each of them.
 */
class ImpureScopeFilterStubModel extends Model
{
    use ScopeFilterStubConcern;

    protected $table = 'scope_filter_stub';

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function joined(Builder $query): void
    {
        $query->join('other_table', 'other_table.id', '=', $this->qualifyColumn('id'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function ordered(Builder $query): void
    {
        $query->where($this->qualifyColumn('price'), '>', 0)->orderBy($this->qualifyColumn('name'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function selected(Builder $query): void
    {
        $query->select($this->qualifyColumn('id'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function grouped(Builder $query): void
    {
        $query->groupBy($this->qualifyColumn('name'))->havingRaw('count(*) > 1');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function limited(Builder $query): void
    {
        $query->limit(1)->offset(2);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function unioned(Builder $query): void
    {
        $query->union(self::query()->where($this->qualifyColumn('price'), '>', 0));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function eagerLoading(Builder $query): void
    {
        $query->with('missing');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function distinctRows(Builder $query): void
    {
        $query->distinct();
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function locked(Builder $query): void
    {
        $query->lockForUpdate();
    }

    /**
     * Adds a binding outside the where conditions and nothing else.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function boundOutsideWhere(Builder $query): void
    {
        $query->getQuery()->addBinding(1, 'select');
    }

    /**
     * Adds its condition through a callback run just before the query, which a nested where never carries.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function deferred(Builder $query): void
    {
        $query->beforeQuery(function (QueryBuilder $base): void {
            $base->where('price', '>', 0);
        });
    }

    /**
     * Adds its condition as a global scope of the nested builder, which is never applied.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function globallyScoped(Builder $query): void
    {
        $query->withGlobalScope('priced', function (Builder $scoped): void {
            $scoped->where('price', '>', 0);
        });
    }

    /**
     * Narrows the rows in a callback run after the query, which a nested where never carries.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function filteredAfterwards(Builder $query): void
    {
        $query->afterQuery(static fn (iterable $rows): iterable => $rows);
    }

    /**
     * The same on the base query builder.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function baseFilteredAfterwards(Builder $query): void
    {
        $query->getQuery()->afterQuery(static fn (iterable $rows): iterable => $rows);
    }

    /**
     * Puts its conditions on another builder, so they would never reach the filtered query.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    #[FilterScope]
    protected function detached(Builder $query): Builder
    {
        return self::query()->where($this->qualifyColumn('price'), '>', 0);
    }
}
