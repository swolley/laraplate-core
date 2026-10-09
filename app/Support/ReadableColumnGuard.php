<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Contracts\IHidesAttributesFromAnonymous;
use Modules\Core\Contracts\IRestrictsReadableColumns;
use Modules\Core\Models\User;

/**
 * Whether the current caller may read a column through a query, where the serialization cannot remove it: a facet
 * field, a group_by key, a sort, a filter or an aggregate. Each would otherwise answer, as counts, buckets or an
 * order, the values the serialization withholds.
 *
 * A column is unreadable when the model hides it from the anonymous user ({@see IHidesAttributesFromAnonymous})
 * and the caller is the anonymous user, or when the model restricts its readable columns for the caller
 * ({@see IRestrictsReadableColumns}, the media projection of the `api` guard) and the column is not among them.
 * Each name of a dotted path is checked on the model it is read on, so a related column obeys the related model.
 */
final class ReadableColumnGuard
{
    /**
     * @throws InvalidArgumentException when a name of the path is a column or a relation the caller may not read
     */
    public static function assertReadablePath(Model $model, string $path): void
    {
        $names = explode('.', $path);

        if (count($names) > 1 && $names[0] === $model->getTable()) {
            array_shift($names);
        }

        $current = $model;
        $last = count($names) - 1;

        foreach ($names as $index => $name) {
            throw_unless(
                self::isReadable($current, $name),
                InvalidArgumentException::class,
                sprintf('"%s" cannot be read: "%s" of %s is not readable by this caller.', $path, $name, $current->getTable()),
            );

            if ($index === $last) {
                return;
            }

            $relation = RelationGuard::relationOf($current, $name);

            // What follows a name that is not a relation is a JSON path or an index field of the same column, and
            // what follows a morph relation depends on each row: both are checked, or refused, elsewhere.
            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                return;
            }

            $current = $relation->getRelated();
        }
    }

    /**
     * Whether the current caller may read the column, or the relation, of the model through a query. A JSON
     * path (`column->key`) is the column it reads.
     */
    public static function isReadable(Model $model, string $name): bool
    {
        $column = Str::before($name, '->');

        if ($model instanceof IHidesAttributesFromAnonymous && self::callerIsAnonymous() && in_array($column, $model->anonymousHiddenAttributes(), true)) {
            return false;
        }

        if ($model instanceof IRestrictsReadableColumns) {
            $readable = $model->readableColumnsForCurrentCaller();

            if ($readable !== null && ! in_array($column, $readable, true)) {
                return false;
            }
        }

        return true;
    }

    private static function callerIsAnonymous(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isGuest();
    }
}
