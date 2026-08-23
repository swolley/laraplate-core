<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use DateTimeInterface;

/**
 * Normalizes a raw cell value from any source reader into the plain string the
 * mapping and importers work with. Dates (openspout hands back `DateTimeImmutable`)
 * become ISO-8601, booleans `1`/`0`, null the empty string.
 */
final class CellStringifier
{
    public static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
