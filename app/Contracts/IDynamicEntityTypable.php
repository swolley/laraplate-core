<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface IDynamicEntityTypable
{
    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array;

    /**
     * Check if value is valid.
     */
    public static function isValid(string $value): bool;

    /**
     * Get validation rules for Laravel.
     */
    public static function validationRule(): string;

    /**
     * Try to get the value from a string.
     */
    public static function tryFrom(string $value): ?static;

    /**
     * Convert the value to a scalar.
     */
    public function toScalar(): string;
}
