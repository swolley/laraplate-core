<?php

declare(strict_types=1);

namespace Modules\Core\Authorization;

/**
 * Marks the window in which the acting user's own roles and permissions are being resolved.
 *
 * Deciding whether somebody may read a row means asking what they are allowed to do, and answering
 * that hydrates rows from the permission tables. Those models carry the same per-row guard
 * (see {@see \Modules\Core\Models\Concerns\HasValidations}), so the question asks itself: reading a
 * role requires being a superadmin, and knowing whether you are one requires reading your roles.
 *
 * The rule is that the authorization machinery does not authorize itself. A read taken while an
 * answer is already being worked out is part of working it out, and passes.
 *
 * Kept here rather than as a static on the trait because a trait's static properties are copied
 * into every using class, so the flag one model sets would be invisible to the next.
 */
final class ResolvingAuthorization
{
    private static int $depth = 0;

    /**
     * Run a callback inside the resolution window.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function resolve(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    public static function inProgress(): bool
    {
        return self::$depth > 0;
    }
}
