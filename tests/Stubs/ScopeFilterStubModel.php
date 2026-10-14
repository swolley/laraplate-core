<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Attributes\FilterScope;

/**
 * A model whose scopes only add where conditions, declared and undeclared, for the ScopeGuard tests.
 */
class ScopeFilterStubModel extends Model
{
    use ScopeFilterStubConcern;

    protected $table = 'scope_filter_stub';

    protected $fillable = ['name', 'price', 'is_flagged', 'released_at'];

    protected $casts = [
        'price' => 'float',
        'is_flagged' => 'boolean',
        'released_at' => 'datetime',
    ];

    /**
     * Legacy `scopeXxx` style: named `legacyNamed` as Eloquent names it.
     *
     * @param  Builder<static>  $query
     */
    #[FilterScope]
    public function scopeLegacyNamed(Builder $query, string $name): void
    {
        $query->where($this->qualifyColumn('name'), $name);
    }

    /**
     * Carries the attribute but is not a named scope, so it can never be called as one.
     *
     * @param  Builder<static>  $query
     */
    #[FilterScope]
    public function notAScope(Builder $query): void
    {
        $query->where($this->qualifyColumn('name'), 'never');
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function priced(Builder $query, int $min, ?float $max = null): void
    {
        $query->where($this->qualifyColumn('price'), '>=', $min);

        if ($max !== null) {
            $query->where($this->qualifyColumn('price'), '<=', $max);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope(client: true)]
    protected function releasedBefore(Builder $query, CarbonInterface $date): void
    {
        $query->where($this->qualifyColumn('released_at'), '<', $date);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function flagged(Builder $query, bool $flag = true): void
    {
        $query->where($this->qualifyColumn('is_flagged'), $flag);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function eitherName(Builder $query, string $first, string $second): void
    {
        $query->where($this->qualifyColumn('name'), $first)->orWhere($this->qualifyColumn('name'), $second);
    }

    /**
     * A local scope without the attribute: never usable as a filter.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function undeclared(Builder $query): void
    {
        $query->where($this->qualifyColumn('price'), '>', 0);
    }

    /**
     * Declared with a parameter a filter cannot carry.
     *
     * @param  Builder<static>  $query
     * @param  list<string>  $names
     */
    #[Scope]
    #[FilterScope]
    protected function namedAnyOf(Builder $query, array $names): void
    {
        $query->whereIn($this->qualifyColumn('name'), $names);
    }
}
