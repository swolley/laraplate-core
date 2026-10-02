<?php

declare(strict_types=1);

namespace App\Casts;

use Modules\Core\Contracts\IDynamicEntityTypable;

/**
 * Test-only entity types of the root application's dynamic contents.
 *
 * Values carry an `app_` prefix so they never collide with module entity types
 * in the shared entities table.
 */
enum EntityType: string implements IDynamicEntityTypable
{
    case Pages = 'app_pages';
    case Authors = 'app_authors';

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if value is valid.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Get validation rules for Laravel.
     */
    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function toScalar(): string
    {
        return $this->value;
    }
}
