<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Carbon;
// use Modules\Core\Casts\ActionEnum;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

// use Illuminate\Validation\UnauthorizedException;

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

    public function initializeHasValidity()
    {
        // if (!isset($this->casts[static::$valid_from_column])) {
        //     $this->casts[static::$valid_from_column] = 'date';
        // }
        // if (!isset($this->casts[static::$valid_to_column])) {
        //     $this->casts[static::$valid_to_column] = 'date';
        // }
        if (!in_array(static::$valid_from_column, $this->fillable)) {
            $this->fillable[] = static::$valid_from_column;
        }
        if (!in_array(static::$valid_to_column, $this->fillable)) {
            $this->fillable[] = static::$valid_to_column;
        }
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date 
     * @return Builder 
     * @throws InvalidArgumentException 
     */
    protected function withValidityFilter(Builder $query, Carbon $date): Builder
    {
        return $query->where($this->qualifyColumn(static::$valid_from_column), '<=', $date)->where(function ($q) use ($date): void {
            $q->where($this->qualifyColumn(static::$valid_to_column), '>=', $date)->orWhereNull($this->qualifyColumn(static::$valid_to_column));
        });
    }

    public function scopeValidityOrdered(Builder $query)
    {
        $query->orderBy(static::$valid_from_column, 'desc')/*->orderBy(static::$valid_to_column, 'desc')*/;
    }

    /**
     * Currently valid records
     * @param Builder $query 
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$valid_from_column), '<=', now())->where(function ($q) {
            $q->where($this->qualifyColumn(static::$valid_to_column), '>=', now())->orWhereNull($this->qualifyColumn(static::$valid_to_column));
        });
    }

    /**
     * Expired records
     * @param Builder $query 
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->withoutGlobalScope('valid')->whereNotNull($this->qualifyColumn(static::$valid_to_column))->where($this->qualifyColumn(static::$valid_to_column), '<', now());
    }

    /**
     * Expired records at a given date
     * @param Builder $query 
     * @param Carbon $date 
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopeExpiredAt(Builder $query, Carbon $date): Builder
    {
        return $query->expired()->validAt($date);
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopeValidAt(Builder $query, Carbon $date): Builder
    {
        return static::withoutGlobalScope('valid')->withValidityFilter($query, $date);
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn(static::$valid_from_column), '<=', now())->where(function ($query) {
            $query->where($this->qualifyColumn(static::$valid_to_column), '>=', now())->orWhereNull($this->qualifyColumn(static::$valid_to_column));
        });
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn(static::$valid_from_column));
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date
     * @return Builder
     * @throws InvalidArgumentException 
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull($this->qualifyColumn(static::$valid_from_column))->where($this->qualifyColumn(static::$valid_from_column), '>', now());
    }

    /**
     * Check if the content is valid at a given date.
     * @throws \InvalidFormatException
     */
    public function isValid(?Carbon $date = null): bool
    {
        if (!$date instanceof \Illuminate\Support\Carbon) {
            $date = Carbon::today();
        }

        return $date->gte($this->{static::$valid_from_column}) && (!$this->{static::$valid_to_column} || $date->lte($this->{static::$valid_to_column}));
    }

    /**
     * Alias for isValid method
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
        return $this->valid_to !== null && $this->valid_to < now();
    }

    /**
     * Check if the content is draft (nor plublished yet).
     */
    public function isDraft(): bool
    {
        return $this->valid_from === null;
    }

    /**
     * Check if the content is scheduled (published in the future).
     */
    public function isScheduled(): bool
    {
        return $this->valid_from !== null && $this->valid_from > now();
    }

    /**
     * Publish the content.
     */
    public function publish(?Carbon $valid_from = null, ?Carbon $valid_to = null): void
    {
        $valid_from ??= now();
        if ($valid_to instanceof \Illuminate\Support\Carbon) {
            $min = min($valid_from, $valid_to);
            $max = max($valid_from, $valid_to);
            $valid_from = $min;
            $valid_to = $max;
        }

        $this->valid_from = $valid_from;
        $this->valid_to = $valid_to;
    }

    /**
     * Unpublish the content.
     */
    public function unpublish(): void
    {
        $this->valid_from = null;
        $this->valid_to = null;
    }
}
