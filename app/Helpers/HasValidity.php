<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;
use InvalidFormatException;

/**
 * @template TModel of Model
 */
trait HasValidity
{
    protected static $valid_from_column = 'valid_from';

    protected static $valid_to_column = 'valid_to';

    public static function validFromKey(): string
    {
        return static::$valid_from_column;
    }

    public static function validToKey(): string
    {
        return static::$valid_to_column;
    }

    public function initializeHasValidity(): void
    {
        if (! in_array(static::$valid_from_column, $this->fillable, true)) {
            $this->fillable[] = static::$valid_from_column;
        }

        if (! in_array(static::$valid_to_column, $this->fillable, true)) {
            $this->fillable[] = static::$valid_to_column;
        }
    }

    /**
     * Check if the content is valid at a given date.
     *
     * @throws InvalidFormatException
     */
    public function isValid(?Carbon $date = null): bool
    {
        if (! $date instanceof Carbon) {
            $date = Date::today();
        }

        return $date->gte($this->{static::$valid_from_column}) && (! $this->{static::$valid_to_column} || $date->lte($this->{static::$valid_to_column}));
    }

    /**
     * Alias for isValid method.
     */
    public function isPublished(?Carbon $date = null): bool
    {
        return $this->isValid($date);
    }

    /**
     * Check if the content is expired.
     */
    public function isExpired(): bool
    {
        return $this->{static::$valid_to_column} !== null && $this->{static::$valid_to_column} < now();
    }

    /**
     * Check if the content is draft (nor plublished yet).
     */
    public function isDraft(): bool
    {
        return $this->{static::$valid_from_column} === null;
    }

    /**
     * Check if the content is scheduled (published in the future).
     */
    public function isScheduled(): bool
    {
        return $this->{static::$valid_from_column} !== null && $this->{static::$valid_from_column} > now();
    }

    /**
     * Publish the content.
     */
    public function publish(?Carbon $valid_from = null, ?Carbon $valid_to = null): void
    {
        $valid_from ??= now();

        if ($valid_to instanceof Carbon) {
            $min = min($valid_from, $valid_to);
            $max = max($valid_from, $valid_to);
            $valid_from = $min;
            $valid_to = $max;
        }

        $this->{static::$valid_from_column} = $valid_from;
        $this->{static::$valid_to_column} = $valid_to;
    }

    /**
     * Unpublish the content.
     */
    public function unpublish(): void
    {
        $this->{static::$valid_from_column} = null;
        $this->{static::$valid_to_column} = null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function validityOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn(static::$valid_from_column), 'desc')/* ->orderBy(static::$valid_to_column, 'desc') */;
    }

    /**
     * Currently valid records.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    protected function scopeValid(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$valid_from_column), '<=', now())->where(function ($q): void {
            $q->where($this->qualifyColumn(static::$valid_to_column), '>=', now())->orWhereNull($this->qualifyColumn(static::$valid_to_column));
        });
    }

    /**
     * Expired records.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    #[Scope]
    protected function expired(Builder $query): Builder
    {
        return $query->withoutGlobalScope('valid')->whereNotNull($this->qualifyColumn(static::$valid_to_column))->where($this->qualifyColumn(static::$valid_to_column), '<', now());
    }

    /**
     * Expired records at a given date.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    #[Scope]
    protected function expiredAt(Builder $query, Carbon $date): Builder
    {
        return $query->expired()->validAt($date);
    }

    /**
     * Filter records by validity on specified date.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    #[Scope]
    protected function validAt(Builder $query, Carbon $date): Builder
    {
        return static::withoutGlobalScope('valid')->withValidityFilter($query, $date);
    }

    /**
     * Filter records by validity on specified date.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$valid_from_column), '<=', now())->where(function ($query): void {
            $query->where($this->qualifyColumn(static::$valid_to_column), '>=', now())->orWhereNull($this->qualifyColumn(static::$valid_to_column));
        });
    }

    /**
     * Filter records by validity on specified date.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    #[Scope]
    protected function draft(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn(static::$valid_from_column));
    }

    /**
     * Filter records by validity on specified date.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    #[Scope]
    protected function scheduled(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn(static::$valid_from_column))->where($this->qualifyColumn(static::$valid_from_column), '>', now());
    }

    /**
     * Filter records by validity on specified date.
     *
     * @param  Builder<static>  $query
     *
     * @throws InvalidArgumentException
     *
     * @return Builder<static>
     */
    protected function withValidityFilter(Builder $query, Carbon $date): Builder
    {
        return $query->where($this->qualifyColumn(static::$valid_from_column), '<=', $date)->where(function ($q) use ($date): void {
            $q->where($this->qualifyColumn(static::$valid_to_column), '>=', $date)->orWhereNull($this->qualifyColumn(static::$valid_to_column));
        });
    }
}
