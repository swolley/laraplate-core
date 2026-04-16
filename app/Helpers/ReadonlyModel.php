<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\Exceptions\ReadOnlyModelException;

/**
 * Prevents create, update, delete, restore, and force-delete on the using model.
 *
 * Bulk writes via {@see \Illuminate\Database\Eloquent\Builder::update()} bypass model events
 * and are not blocked; use application-level constraints or {@see Model::withoutEvents()} with care.
 */
trait ReadonlyModel
{
    /**
     * Depth counter for {@see static::withoutReadOnlyGuards()}; each nested call increments then decrements.
     */
    private static int $read_only_guards_bypass_depth = 0;

    /**
     * Run a callback while allowing persistence for this model class only.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutReadOnlyGuards(callable $callback): mixed
    {
        static::$read_only_guards_bypass_depth++;

        try {
            return $callback();
        } finally {
            static::$read_only_guards_bypass_depth--;
        }
    }

    public static function bootReadonlyModel(): void
    {
        static::creating(function (Model $model): void {
            if (static::readOnlyGuardsAreBypassed()) {
                return;
            }

            throw new ReadOnlyModelException(
                sprintf('Cannot create model [%s]: the model is read-only.', $model::class)
            );
        });

        static::updating(function (Model $model): void {
            if (static::readOnlyGuardsAreBypassed()) {
                return;
            }

            throw new ReadOnlyModelException(
                sprintf('Cannot update model [%s]: the model is read-only.', $model::class)
            );
        });

        static::deleting(function (Model $model): void {
            if (static::readOnlyGuardsAreBypassed()) {
                return;
            }

            throw new ReadOnlyModelException(
                sprintf('Cannot delete model [%s]: the model is read-only.', $model::class)
            );
        });

        if (method_exists(static::class, 'restoring')) {
            static::restoring(function (Model $model): void {
                if (static::readOnlyGuardsAreBypassed()) {
                    return;
                }

                throw new ReadOnlyModelException(
                    sprintf('Cannot restore model [%s]: the model is read-only.', $model::class)
                );
            });
        }

        if (method_exists(static::class, 'forceDeleting')) {
            static::forceDeleting(function (Model $model): void {
                if (static::readOnlyGuardsAreBypassed()) {
                    return;
                }

                throw new ReadOnlyModelException(
                    sprintf('Cannot force-delete model [%s]: the model is read-only.', $model::class)
                );
            });
        }
    }

    private static function readOnlyGuardsAreBypassed(): bool
    {
        return static::$read_only_guards_bypass_depth > 0;
    }
}
