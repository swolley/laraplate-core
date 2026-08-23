<?php

declare(strict_types=1);

namespace Modules\Core\Import\Enums;

/**
 * The file shape a bulk import reads from. Each maps to one source reader; the
 * value is also the canonical extension. SQL dumps are deliberately absent — a raw
 * dump is a security concern and is a gated follow-up, not part of this surface.
 */
enum ImportSourceFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Ods = 'ods';
    case Json = 'json';

    /**
     * Resolve a format from a file extension, case-insensitively. `null` when the
     * extension is not a supported import format.
     */
    public static function fromExtension(string $extension): ?self
    {
        return self::tryFrom(mb_strtolower(mb_trim($extension, ". \t\n\r")));
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
