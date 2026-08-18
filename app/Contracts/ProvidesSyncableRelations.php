<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Opt-in contract for models whose many-to-many relations may be reassigned
 * through the generic CRUD `update` operation.
 *
 * The generic update writes only fillable columns; relation membership lives in
 * pivot tables and is never touched by a column update. A model implementing this
 * contract whitelists the relations a client may `sync` via an `update` payload's
 * `relations` map — any relation not listed here is rejected, so the whitelist is
 * the authorization boundary for relation writes.
 */
interface ProvidesSyncableRelations
{
    /**
     * Names of the belongsToMany / morphToMany relations that the generic CRUD
     * update may sync from a list of ids.
     *
     * @return list<string>
     */
    public function syncableRelations(): array;
}
