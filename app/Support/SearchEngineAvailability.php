<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Throwable;

/**
 * Tells a search engine being unreachable apart from a genuine indexing error.
 *
 * Transport failures (server down, DNS, refused connection, timeout) must not
 * abort a domain write, while schema or payload errors still have to surface.
 * Engine SDK classes are matched by name so an engine that is not installed
 * does not have to be a hard dependency.
 */
final class SearchEngineAvailability
{
    /**
     * @var list<string>
     */
    private const array UNAVAILABLE_TYPES = [
        \Psr\Http\Client\NetworkExceptionInterface::class,
        \GuzzleHttp\Exception\ConnectException::class,
        \Illuminate\Http\Client\ConnectionException::class,
        \Elastic\Transport\Exception\NoNodeAvailableException::class,
        \Typesense\Exceptions\ServiceUnavailable::class,
        \Typesense\Exceptions\HTTPStatus0Error::class,
        \Typesense\Exceptions\Timeout::class,
    ];

    /**
     * How many strict scopes are open. While any is, an unreachable engine is not tolerated.
     */
    private static int $strict_depth = 0;

    /**
     * Runs the callback with an unreachable engine treated as a failure, not tolerated.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function strictly(callable $callback): mixed
    {
        self::$strict_depth++;

        try {
            return $callback();
        } finally {
            self::$strict_depth--;
        }
    }

    public static function isStrict(): bool
    {
        return self::$strict_depth > 0;
    }

    /**
     * Whether the failure (or any failure it wraps) means the engine could not be reached.
     */
    public static function isUnreachable(Throwable $exception): bool
    {
        $current = $exception;

        while ($current instanceof Throwable) {
            foreach (self::UNAVAILABLE_TYPES as $type) {
                if ((class_exists($type) || interface_exists($type)) && $current instanceof $type) {
                    return true;
                }
            }

            $current = $current->getPrevious();
        }

        return false;
    }
}
