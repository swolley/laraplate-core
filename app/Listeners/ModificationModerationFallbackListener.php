<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Modules\Core\Events\ModificationRequiresModeration;

/**
 * No-op when AI does not handle moderation; humans continue the approval workflow.
 */
final class ModificationModerationFallbackListener
{
    public function handle(ModificationRequiresModeration $event): void
    {
        if ($event->isHandled()) {
            return;
        }
    }
}
