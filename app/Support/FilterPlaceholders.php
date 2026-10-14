<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\User;

/**
 * Dynamic values that a JSON-persisted setting cannot spell out: the ACL filters and the cron parameters are
 * saved as literals, so a moving target such as the current time is written as a placeholder and replaced by
 * its live value when the setting is used.
 *
 * A value equal to a known placeholder is substituted (`@now`, `@today`, `@user.<attribute>`); an array is
 * resolved element by element, and any other value is returned untouched.
 */
final class FilterPlaceholders
{
    /**
     * @param  bool  $withUser  Whether `@user.<attribute>` is read from the acting user. A cron job has no
     *                          acting user, so it leaves that placeholder as it is.
     */
    public static function resolve(mixed $value, bool $withUser = true): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::resolve($item, $withUser), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($withUser && self::isUserAttribute($value)) {
            return self::userAttribute(mb_substr($value, 6));
        }

        return match ($value) {
            '@now' => Carbon::now(),
            '@today' => Carbon::today(),
            default => $value,
        };
    }

    /**
     * Whether the value is `@now` or `@today`, the placeholders that resolve to a date.
     */
    public static function isDate(mixed $value): bool
    {
        return $value === '@now' || $value === '@today';
    }

    /**
     * Whether the value is a `@user.<attribute>` placeholder, read from the acting user when it is resolved.
     */
    public static function isUserAttribute(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, '@user.');
    }

    /**
     * Whether the value is any placeholder {@see resolve()} replaces.
     */
    public static function isPlaceholder(mixed $value): bool
    {
        return self::isDate($value) || self::isUserAttribute($value);
    }

    /**
     * Resolves `@user.<attribute>` against the acting user.
     *
     * Deliberately a generic attribute reader rather than a hard-coded `@user.id`: the same implementation
     * answers `@user.department_id`, `@user.team_id` and `@user.tenant_id`, which are the cuts an ACL actually
     * needs, and each one added by hand would be the same three lines.
     *
     * Only scalar, non-hidden attributes resolve. Hidden attributes are excluded so no setting, however it is
     * written, can pull a password hash or a remember token into a query, and relations and accessors
     * returning objects are excluded because the value has to be comparable in SQL. Anything that does not
     * resolve becomes null, which matches nothing rather than everything.
     */
    private static function userAttribute(string $attribute): mixed
    {
        $user = Auth::user();

        if (! $user instanceof User || $attribute === '') {
            return null;
        }

        // Not `getAttribute()` alone: outside production a missing attribute throws, and this must give null.
        if (in_array($attribute, $user->getHidden(), true) || ! $user->hasAttribute($attribute)) {
            return null;
        }

        $value = $user->getAttribute($attribute);

        return is_scalar($value) ? $value : null;
    }
}
