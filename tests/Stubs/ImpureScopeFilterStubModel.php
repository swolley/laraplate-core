<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
