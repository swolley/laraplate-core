<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Core\Models\RecordOrigin;

/**
 * The import origin of a record hangs from it by a polymorphic key, with no foreign key to take it along. Left
 * behind after a hard delete, it maps the external id to a local id that no longer exists, and the next import
 * of that source fails on it. A record deleted for good therefore takes its origin along; one moved to the bin
 * keeps it, so a restore is still recognised by the importer.
 *
 * Listens on `eloquent.deleted: *`. Pivots are skipped: they are never imported, and every `sync()` deletes some.
 */
final class RemoveOriginOfDeletedRecord
{
    /**
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;

        // A soft delete leaves the row, and the model, existing.
        if (! $model instanceof Model || $model->exists || $model instanceof Pivot || $model instanceof RecordOrigin) {
            return;
        }

        $origin = new RecordOrigin;
        $origin->setConnection($model->getConnectionName());

        $origin->newQuery()
            ->toBase()
            ->where('referable_type', $model->getMorphClass())
            ->where('referable_id', $model->getKey())
            ->delete();
    }
}
