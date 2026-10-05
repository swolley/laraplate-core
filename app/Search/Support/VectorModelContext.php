<?php

declare(strict_types=1);

namespace Modules\Core\Search\Support;

use Closure;

/**
 * Names the embedding model whose vectors an index document must carry.
 *
 * Outside {@see self::using()} it is the active model (`core.search.vector.model`,
 * the `provider:model` string stamped in `core_model_embeddings.model_key`).
 * Inside it the override wins, so a switch can index the incoming model's
 * vectors while the active model is still the previous one.
 */
final class VectorModelContext
{
    private static ?string $override = null;

    /**
     * Model key to serialize, or null when none is configured (all rows).
     */
    public static function get(): ?string
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $configured = config('core.search.vector.model');

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    /**
     * Run the callback with the given model key as the override, restoring the
     * previous override afterwards, also when the callback throws.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function using(string $modelKey, Closure $callback): mixed
    {
        $previous = self::$override;
        self::$override = $modelKey;

        try {
            return $callback();
        } finally {
            self::$override = $previous;
        }
    }
}
