<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

use Carbon\CarbonImmutable;

/**
 * The calendar units an aggregate cuts a period into. A bucket is named by the date of its first day: a week
 * starts on the Monday (ISO), whatever the locale of the date, and the others on the first day of the month,
 * of the quarter and of the year.
 */
enum TimeBucket: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * The bucket a client names, or null when the contract has no such bucket. The match is exact.
     */
    public static function fromInput(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * The first day of the bucket that holds the day, at the start of that day.
     */
    public function start(CarbonImmutable $day): CarbonImmutable
    {
        return match ($this) {
            self::Day => $day->startOfDay(),
            self::Week => $day->startOfWeek(CarbonImmutable::MONDAY),
            self::Month => $day->startOfMonth(),
            self::Quarter => $day->startOfQuarter(),
            self::Year => $day->startOfYear(),
        };
    }

    /**
     * The first day of the bucket after the one that starts on `$start`, which is a date {@see self::start()} gave.
     */
    public function next(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::Day => $start->addDay(),
            self::Week => $start->addWeek(),
            self::Month => $start->addMonth(),
            self::Quarter => $start->addQuarter(),
            self::Year => $start->addYear(),
        };
    }

    /**
     * The key of a bucket in a result: the date of its first day, as `Y-m-d`.
     */
    public function key(CarbonImmutable $start): string
    {
        return $start->format('Y-m-d');
    }
}
