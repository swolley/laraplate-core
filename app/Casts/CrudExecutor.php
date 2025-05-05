<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use ReflectionClass;

final class CrudExecutor
{
    public const string SELECT = 'get';

    public const string COUNT = 'count';

    public const string INSERT = 'create';

    public const string UPDATE = 'save';

    public const string DELETE = 'delete';

    public const string FORCE_DELETE = 'force_delete';

    public const string RESTORE = 'restore';

    /**
     * gets value if exists.
     */
    public static function tryFrom(string $value): ?string
    {
        $values = self::values();

        foreach ($values as $const) {
            if ($const === $value) {
                return $const;
            }
        }

        return null;
    }

    /**
     * returns if is a write action.
     */
    public static function isWriteAction(string $action): bool
    {
        return match ($action) {
            self::INSERT, self::UPDATE, self::DELETE, self::FORCE_DELETE, self::RESTORE => true,
            default => false,
        };
    }

    /**
     * returns if is a read action.
     */
    public static function isReadAction(string $action): bool
    {
        return ! self::isWriteAction($action);
    }

    /**
     * get all available values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        $refl = new ReflectionClass(self::class);
        $constants = $refl->getConstants();

        return array_values($constants);
    }
}
