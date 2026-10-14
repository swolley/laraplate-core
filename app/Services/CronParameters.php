<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use DateTimeInterface;
use InvalidArgumentException;
use Modules\Core\Support\FilterPlaceholders;

/**
 * The parameters of a cron job: a list of strings, each one a flag (`--all`), an option with a value
 * (`--limit=10`) or an argument (`Taylor Otwell`), in the order they are given to the command.
 *
 * Laravel's scheduler writes a value into the shell line without escaping it when it starts with a dash,
 * so what starts with a dash is held to a strict shape: a plain name, and for `--name=value` a value that
 * does not start with a dash itself. Everything else is escaped by Laravel.
 *
 * A value or argument that is `@now` or `@today` is replaced by that date each time the parameters are converted,
 * which is each time the scheduler is built, so it is the time of the run and not of the day it was saved. This is what keeps a table that
 * administrators edit from becoming a way to run something else.
 */
final class CronParameters
{
    private const string LONG_OPTION = '/^--[A-Za-z][A-Za-z0-9_-]*$/';

    private const string SHORT_OPTION = '/^-[A-Za-z][A-Za-z0-9]*$/';

    public static function isValidToken(mixed $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        if (! str_starts_with($token, '-')) {
            return true;
        }

        if (! str_starts_with($token, '--')) {
            return preg_match(self::SHORT_OPTION, $token) === 1;
        }

        [$name, $value] = array_pad(explode('=', $token, 2), 2, null);

        return preg_match(self::LONG_OPTION, $name) === 1 && ($value === null || ! str_starts_with($value, '-'));
    }

    public static function isValidList(mixed $tokens): bool
    {
        return is_array($tokens)
            && array_is_list($tokens)
            && array_all($tokens, static fn (mixed $token): bool => self::isValidToken($token));
    }

    /**
     * The array that `Schedule::command()` takes: flags and arguments stay as they are, and an option with a
     * value becomes a name and a value, so that Laravel escapes the value. A repeated option becomes a list.
     *
     * @param  array<int, mixed>  $tokens
     *
     * @throws InvalidArgumentException When an element would not be escaped by Laravel
     *
     * @return array<int|string, string|list<string>>
     */
    public static function toScheduleParameters(array $tokens): array
    {
        $parameters = [];

        foreach ($tokens as $token) {
            if (! self::isValidToken($token)) {
                throw new InvalidArgumentException(sprintf('Invalid cron parameter "%s".', is_string($token) ? $token : get_debug_type($token)));
            }

            if (! str_starts_with($token, '-')) {
                $parameters[] = self::resolved($token);

                continue;
            }

            if (! str_starts_with($token, '--') || ! str_contains($token, '=')) {
                $parameters[] = $token;

                continue;
            }

            [$name, $value] = explode('=', $token, 2);
            $value = self::resolved($value);

            $parameters[$name] = array_key_exists($name, $parameters) ? [...(array) $parameters[$name], $value] : $value;
        }

        return $parameters;
    }

    /**
     * A value that is a time placeholder (`@now`, `@today`) becomes the date it stands for, as ISO 8601; any other
     * value, a user placeholder included since a cron job has no acting user, is left as it is.
     */
    private static function resolved(string $value): string
    {
        $resolved = FilterPlaceholders::resolve($value, withUser: false);

        return $resolved instanceof DateTimeInterface ? $resolved->format(DateTimeInterface::ATOM) : $value;
    }
}
