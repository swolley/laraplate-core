<?php

declare(strict_types=1);

namespace Modules\Core\Search\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * The embeddings of a record hang from it by a polymorphic key, with no foreign key to take them along, and its
 * translations leave by cascade, without the events that would remove them per locale. A record deleted for
 * good therefore removes its embeddings; one moved to the bin keeps them for its restore.
 *
 * A trait of its own, used by {@see Searchable}: Scout's trait already defines `bootSearchable()`.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait RemovesEmbeddingsWithRecord
{
    public static function bootRemovesEmbeddingsWithRecord(): void
    {
        static::deleted(static function (Model $model): void {
            // A soft delete leaves the row, and the model, existing.
            if ($model->exists || ! method_exists($model, 'embeddings')) {
                return;
            }

            $model->embeddings()->delete();
        });
    }
}
