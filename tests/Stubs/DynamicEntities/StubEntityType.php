<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\DynamicEntities;

use Modules\Core\Contracts\IDynamicEntityTypable;

/**
 * Entity types for Core's dynamic-content test stubs, standing in for a module's own enum.
 */
enum StubEntityType: string implements IDynamicEntityTypable
{
    case Pages = 'pages';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function toScalar(): string
    {
        return $this->value;
    }
}
