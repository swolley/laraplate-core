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

    protected function casts()
    {
        return [
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public static function validFromKey(): string
    {
        return static::$valid_from_column;
    }

    public static function validToKey(): string
    {
        return static::$valid_to_column;
    }

    protected static function bootHasValidity(): void
    {
        // function check_authorization(Model $model): void
        // {
        //     $user = Auth::user();
        //     if ($user && !$user->hasRole(($model->getConnection() ?? 'default') . '.' . $model->getTable() . '.' . ActionEnum::APPROVE->value)) {
        //         throw new UnauthorizedException('User ' . $user->name . ' is not authorized to publish the model');
        //     }
        // }

        // static::addGlobalScope('valid', function (Builder $query): void {
        //     static::withValidityFilter($query, Carbon::today());
        // });

        // static::creating(function (Model $model): void {
        //     if (($model->isDirty($model->validFromKey()) && $model->{$model->validFromKey()} !== null) || ($model->isDirty($model->validToKey()) && $model->{$model->validToKey()} !== null)) {
        //         check_authorization($model);
        //     }
        // });
        // static::updating(function (Model $model): void {
        //     if ($model->isDirty($model->validFromKey()) || $model->isDirty($model->validToKey())) {
        //         check_authorization($model);
        //     }
        // });
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date 
     * @return Builder 
     * @throws InvalidArgumentException 
     */
    protected static function withValidityFilter(Builder $query, Carbon $date): Builder
    {
        return $query->where(static::$valid_from_column, '<=', $date)->where(function ($q) use ($date): void {
            $q->where(static::$valid_to_column, '>=', $date)->orWhereNull(static::$valid_to_column);
        });
    }

    /**
     * Currently valid records
     * @param Builder $query 
     * @throws InvalidArgumentException 
     */
    protected function scopeValid(Builder $query): void
    {
        $query->where(static::$valid_from_column, '<=', now())->where(function ($q) {
            $q->where(static::$valid_to_column, '>=', now())->orWhereNull(static::$valid_to_column);
        });
    }

    /**
     * Expired records
     * @param Builder $query 
     * @throws InvalidArgumentException 
     */
    protected function scopeExpired(Builder $query): void
    {
        $query->withoutGlobalScope('valid')->whereNotNull('valid_to')->where('valid_to', '<', now());
    }

    /**
     * Expired records at a given date
     * @param Builder $query 
     * @param Carbon $date 
     * @throws InvalidArgumentException 
     */
    protected function scopeExpiredAt(Builder $query, Carbon $date): void
    {
        $query->expired()->validAt($date);
    }

    /**
     * Filter records by validity on specified date
     * @param Builder $query 
     * @param Carbon $date
     * @throws InvalidArgumentException 
     */
    public function scopeValidAt(Builder $query, Carbon $date): void
    {
        static::withoutGlobalScope('valid')->withValidityFilter($query, $date);
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
