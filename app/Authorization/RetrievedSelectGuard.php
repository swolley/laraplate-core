<?php

declare(strict_types=1);

namespace Modules\Core\Authorization;

/**
 * Process-scoped registry of model classes whose per-row `retrieved` select check
 * (see {@see \Modules\Core\Models\Concerns\HasValidations}) is temporarily suppressed.
 *
 * The CRUD read engine already ensures the table-level `select` permission once and
 * applies row-level ACL filters before hydrating, so re-running the identical
 * table-level check for every hydrated row is pure overhead on a list. Suppression is
 * scoped to a single class and a single call (push/pop around the query execution), so
 * eager-loaded relations and every read outside such a context stay fully guarded.
 */
final class RetrievedSelectGuard
{
    /**
     * @var array<class-string, int>
     */
    private static array $suppressed = [];

    /**
     * Run a callback with the per-row select check suppressed for the given class.
     *
     * @template TReturn
     *
     * @param  class-string  $modelClass
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function without(string $modelClass, callable $callback): mixed
    {
        self::$suppressed[$modelClass] = (self::$suppressed[$modelClass] ?? 0) + 1;

        try {
            return $callback();
        } finally {
            if (--self::$suppressed[$modelClass] <= 0) {
                unset(self::$suppressed[$modelClass]);
            }
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    public static function isSuppressed(string $modelClass): bool
    {
        return isset(self::$suppressed[$modelClass]);
    }
}
