<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
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
     * Legacy method shadowed by the `#[Scope]` method of the same name, which Eloquent calls instead.
     *
     * @param  Builder<static>  $query
     */
    #[FilterScope]
    public function scopeShadowed(Builder $query): void
    {
        $query->where($this->qualifyColumn('name'), 'legacy');
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
     * Refuses a short window with its own exception, so a caller can tell it from a guard refusal; the declared
     * default is the only value the save-time check may run it with when the parameter is a placeholder.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException when the window is shorter than a day
     */
    #[Scope]
    #[FilterScope]
    protected function releasedWithin(Builder $query, int $hours = 48): void
    {
        if ($hours < 24) {
            throw new InvalidArgumentException('The window must be at least a day.');
        }

        $query->where($this->qualifyColumn('released_at'), '>=', now()->subHours($hours));
    }

    /**
     * Removes a global scope from the throwaway builder it runs on, as the validity scopes do; the filtered query
     * keeps its own global scopes.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    protected function withoutItsGlobalScope(Builder $query): void
    {
        $query->withoutGlobalScope('archived')->where($this->qualifyColumn('price'), '>', 0);
    }

    /**
     * The scope Eloquent calls for `shadowed`; it carries no attribute, so `shadowed` is not a filter scope.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function shadowed(Builder $query): void
    {
        $query->where($this->qualifyColumn('name'), 'attribute');
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

    /**
     * Declared on a private method, which Eloquent cannot call as a scope: ignored.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    #[FilterScope]
    private function hiddenAway(Builder $query): void
    {
        $query->where($this->qualifyColumn('name'), 'hidden');
    }
}
