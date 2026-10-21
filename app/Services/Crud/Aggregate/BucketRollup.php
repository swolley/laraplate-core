<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;

/**
 * Rolls the partials of the days of a time bucket up into the figure of the bucket (spec 4.3), in decimal
 * arithmetic so that money adds up exactly: a sum of `0.1` and `0.2` is `0.3`.
 *
 * Every value is the text of a partial as the database gave it, or null when no row gave one, and so is every
 * result: the engine turns it into the number of the contract.
 */
final class BucketRollup
{
    /**
     * The scale of an average, as the money of the ERP is kept.
     */
    private const int AVERAGE_SCALE = 4;

    /**
     * The sum of the partial sums, as a plain decimal at the largest scale among them; null when every value is null.
     *
     * @param  list<?string>  $values
     */
    public static function sum(array $values): ?string
    {
        $present = self::present($values);

        if ($present === []) {
            return null;
        }

        return (string) BigDecimal::sum(...$present);
    }

    /**
     * The average of a bucket: the sum of its values over the count of its non-null values, at scale 4, half up;
     * null when no value was counted.
     */
    public static function avg(?string $sum, int $count): ?string
    {
        if ($sum === null || $count === 0) {
            return null;
        }

        return (string) BigDecimal::of($sum)->dividedBy($count, self::AVERAGE_SCALE, RoundingMode::HalfUp);
    }

    /**
     * The least of the values, nulls ignored. See {@see self::extreme()} for how they are compared.
     *
     * @param  list<?string>  $values
     */
    public static function min(array $values): ?string
    {
        return self::extreme($values, -1);
    }

    /**
     * The greatest of the values, nulls ignored. See {@see self::extreme()} for how they are compared.
     *
     * @param  list<?string>  $values
     */
    public static function max(array $values): ?string
    {
        return self::extreme($values, 1);
    }

    /**
     * The value that sorts first in the direction (`-1` the least, `1` the greatest), returned as it was given.
     *
     * The values are compared as numbers when every one of them is a number, and as plain text otherwise: the
     * minimum or the maximum of a date column is the text the database holds, and an ISO date sorts as text.
     *
     * @param  list<?string>  $values
     */
    private static function extreme(array $values, int $direction): ?string
    {
        $present = self::present($values);

        if ($present === []) {
            return null;
        }

        $numbers = self::numbers($present);
        $best = 0;

        foreach ($present as $index => $value) {
            $order = $numbers !== null
                ? $numbers[$index]->compareTo($numbers[$best])
                : strcmp($value, $present[$best]);

            if ($order * $direction > 0) {
                $best = $index;
            }
        }

        return $present[$best];
    }

    /**
     * Each value as a decimal, or null when one of them is not a number.
     *
     * @param  list<string>  $values
     * @return list<BigDecimal>|null
     */
    private static function numbers(array $values): ?array
    {
        $numbers = [];

        foreach ($values as $value) {
            try {
                $numbers[] = BigDecimal::of($value);
            } catch (MathException) {
                return null;
            }
        }

        return $numbers;
    }

    /**
     * @param  list<?string>  $values
     * @return list<string>
     */
    private static function present(array $values): array
    {
        return array_values(array_filter($values, static fn (?string $value): bool => $value !== null));
    }
}
