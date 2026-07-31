<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use BackedEnum;

final class ValueComparator
{
    /**
     * Compare decoded values so JSON key order, 1 vs 1.0, or an enum against
     * its backing value never read as drift.
     */
    public static function equal(mixed $left, mixed $right): bool
    {
        return self::normalize($left) === self::normalize($right);
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_string($value) && json_validate($value)) {
            $value = json_decode($value, true);
        }

        if (is_array($value)) {
            ksort($value);

            return array_map(static fn (mixed $item): mixed => self::normalize($item), $value);
        }

        return is_numeric($value) ? (string) (float) $value : $value;
    }
}
