<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Events\ModificationPreProcessingCompleted;
use Modules\Core\Events\ModificationRequiresModeration;

final class FinalizeModificationModerationListener
{
    public function handle(ModificationPreProcessingCompleted $event): void
    {
        $cache_key = ModificationRequiresModeration::cacheKey($event->modification);

        $moderation_event = Cache::get($cache_key);

        if (! $moderation_event instanceof ModificationRequiresModeration) {
            return;
        }

        $moderation_event->markPreProcessingCompleted($event->processing_type);

        if ($moderation_event->allPreProcessingCompleted()) {
            Cache::forget($cache_key);

            return;
        }

        Cache::put($cache_key, $moderation_event, now()->addMinutes(ModificationRequiresModeration::CACHE_TTL_MINUTES));
    }
}
