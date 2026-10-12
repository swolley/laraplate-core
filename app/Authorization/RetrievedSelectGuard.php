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
 *
 * {@see self::forSystemRead()} suppresses the check for every class, for reads the
 * application takes on its own behalf and never hands to the acting user.
 */
final class RetrievedSelectGuard
{
    /**
     * @var array<class-string, int>
     */
    private static array $suppressed = [];

    private static int $systemReads = 0;

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
     * Run a callback with the per-row select check suppressed for every class.
     *
     * For a read the application takes on its own behalf, whose rows never reach the acting
     * user: re-indexing a content in the search engine after one of its parts changed, for
     * instance, reads the content and every relation of its search document whatever the
     * caller who changed the part may read. Search results are filtered again when they are
     * served. Only the select check is lifted: writes inside the callback stay guarded.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function forSystemRead(callable $callback): mixed
    {
        self::$systemReads++;

        try {
            return $callback();
        } finally {
            self::$systemReads--;
        }
    }

    /**
     * @param  class-string  $modelClass
     */
    public static function isSuppressed(string $modelClass): bool
    {
        return self::$systemReads > 0 || isset(self::$suppressed[$modelClass]);
    }
}
