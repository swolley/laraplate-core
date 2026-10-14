<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Attributes\FilterScope;

/**
 * A model whose client scopes read other rows through a subquery or raw SQL: only where conditions, so an ACL may use
 * them, but a client may not, since the rows they reach are never checked against the related entity's ACL.
 */
class SubqueryScopeFilterStubModel extends Model
{
    protected $table = 'scope_filter_stub';

    /**
     * @return HasMany<self, $this>
     */
    public function namesakes(): HasMany
    {
        return $this->hasMany(self::class, 'name', 'name');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function withFlaggedNamesake(Builder $query): void
    {
        $query->whereHas('namesakes', fn (Builder $related): Builder => $related->where('is_flagged', true));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function withoutNamesake(Builder $query): void
    {
        $query->where($this->qualifyColumn('price'), '>', 0)->where(fn (Builder $nested): Builder => $nested->doesntHave('namesakes'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function withManyNamesakes(Builder $query): void
    {
        $query->has('namesakes', '>=', 2);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function inSubquery(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('id'), fn (QueryBuilder $sub): QueryBuilder => $sub->select('id')->from('scope_filter_stub')->where('is_flagged', true));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function comparedToSubquery(Builder $query): void
    {
        $query->where($this->qualifyColumn('price'), '>', fn (QueryBuilder $sub): QueryBuilder => $sub->selectRaw('avg(price)')->from('scope_filter_stub'));
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function rawCondition(Builder $query): void
    {
        $query->whereRaw('price > (select avg(price) from scope_filter_stub)');
    }

    /**
     * Plain column conditions only: a client may use it.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function cheapOrUnnamed(Builder $query): void
    {
        $query->where($this->qualifyColumn('price'), '<', 5)->orWhere(fn (Builder $nested): Builder => $nested->whereNull($this->qualifyColumn('name')));
    }
}
