<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Models\Media;
use Modules\Core\Models\ModelEmbedding;

/**
 * Keeps a media's owner and vectors in step with the media's life after claim (M19). The
 * owner's document carries the media surrogate, so every change reindexes the owner:
 * an edit (including the claim itself, which sets the owner), a soft delete and a
 * restore. A force delete also drops the media's own embedding rows.
 */
final class MediaLifecycleObserver
{
    public function updated(Media $media): void
    {
        $media->reindexOwner();
    }

    public function deleted(Media $media): void
    {
        $media->reindexOwner();
    }

    public function restored(Media $media): void
    {
        $media->reindexOwner();
    }

    public function forceDeleted(Media $media): void
    {
        ModelEmbedding::query()
            ->where('model_type', $media->getMorphClass())
            ->where('model_id', $media->getKey())
            ->delete();

        $media->reindexOwner();
    }
}
