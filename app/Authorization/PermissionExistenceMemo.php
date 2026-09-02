<?php

declare(strict_types=1);

namespace Modules\Core\Authorization;

use Illuminate\Database\Eloquent\Model;

/**
 * Request-scoped memo answering "is this permission name registered?".
 *
 * The `once()` call deliberately lives here rather than inline in
 * {@see \Modules\Core\Models\Concerns\HasValidations}. Onceable derives part of its key
 * from the closure's *called* class, so a closure created inside a trait method that
 * models invoke as `static::checkUserCanDo()` gets one memo per composing model class:
 * two models sharing a table name would each pay their own existence query. Owning the
 * closure in a final class keeps the key class-independent, which is what the memo is
 * for — the answer depends on the permission name alone.
 */
final class PermissionExistenceMemo
{
    /**
     * @param  class-string<Model>  $permissionClass  The configured permission model.
     * @param  string  $permission  Full permission name, e.g. `default.orders.select`.
     */
    public static function exists(string $permissionClass, string $permission): bool
    {
        // Both captured values are scalars, so the memo key carries the permission
        // identity and stays stable for the whole request.
        return once(fn (): bool => (new $permissionClass)->newQuery()
            ->where('name', $permission)
            ->exists());
    }
}
