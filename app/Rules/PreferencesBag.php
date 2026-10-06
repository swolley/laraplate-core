<?php

declare(strict_types=1);

namespace Modules\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use Override;

/**
 * The generic limits of a user's preferences bag: a JSON object whose top-level keys are
 * namespaces, a client owning one of them. The limits are cosmetic-data hygiene, not
 * authorization: preferences are never read to decide what a user may see.
 *
 * Size is counted on the whole bag, so a write is checked twice: the payload by this rule,
 * and the bag it merges into through {@see self::fits()}.
 */
final class PreferencesBag implements ValidationRule
{
    /**
     * Serialized size of the whole bag.
     */
    public const int MAX_BYTES = 65536;

    /**
     * Levels of arrays, the bag itself being the first.
     */
    public const int MAX_DEPTH = 6;

    /**
     * A namespace without delimiters, as a route constraint can take it.
     */
    public const string NAMESPACE_REGEX = '[a-z][a-z0-9_.-]{0,39}';

    public static function isNamespace(string $key): bool
    {
        return preg_match('/^' . self::NAMESPACE_REGEX . '$/', $key) === 1;
    }

    /**
     * Whether the bag, once merged, is still within the size limit.
     *
     * @param  array<string, mixed>  $bag
     */
    public static function fits(array $bag): bool
    {
        try {
            return mb_strlen(json_encode($bag, JSON_THROW_ON_ERROR), '8bit') <= self::MAX_BYTES;
        } catch (JsonException) {
            return false;
        }
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail("The {$attribute} must be an object.");

            return;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! self::isNamespace($key)) {
                $fail("The {$attribute} keys must be namespaces: lower case, starting with a letter, at most 40 characters of letters, digits, dots, dashes and underscores.");

                return;
            }
        }

        $depth = self::depthOf($value);

        if ($depth === null) {
            $fail("The {$attribute} must hold only JSON values: text, numbers, booleans, null, lists and objects.");

            return;
        }

        if ($depth > self::MAX_DEPTH) {
            $fail("The {$attribute} may nest at most " . self::MAX_DEPTH . ' levels.');

            return;
        }

        if (! self::fits($value)) {
            $fail("The {$attribute} may not exceed " . self::MAX_BYTES . ' bytes.');
        }
    }

    /**
     * Levels of arrays in the value, or null when it holds something JSON cannot carry.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function depthOf(array $value): ?int
    {
        $deepest = 0;

        foreach ($value as $item) {
            if (is_array($item)) {
                $depth = self::depthOf($item);

                if ($depth === null) {
                    return null;
                }

                $deepest = max($deepest, $depth);

                continue;
            }

            if (! self::isJsonScalar($item)) {
                return null;
            }
        }

        return $deepest + 1;
    }

    private static function isJsonScalar(mixed $value): bool
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => true,
            is_float($value) => is_finite($value),
            is_string($value) => mb_check_encoding($value, 'UTF-8'),
            default => false,
        };
    }
}
