<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Media\MediaMetadataService;
use Modules\Core\Models\Media;

/**
 * Fills a media's deterministic embedded metadata into `custom_properties` when
 * the media row is created (M2). Runs for every media (drafts included — it is
 * cheap); indexing and AI analysis are gated separately on claim (M14). Writes
 * via {@see MediaMetadataService} with `saveQuietly()` to avoid a recursive save.
 */
final class MediaMetadataObserver
{
    public function __construct(private readonly MediaMetadataService $service) {}

    public function created(Media $media): void
    {
        $this->service->writeFor($media);
    }
}
