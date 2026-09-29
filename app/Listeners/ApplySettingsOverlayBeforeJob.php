<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;

/**
 * Re-applies database settings to config before every queued job.
 *
 * A worker boots once, so the boot-time overlay in CoreServiceProvider alone would freeze
 * database-backed config for the worker's lifetime. The worker drops scoped instances before
 * each job, so the resolver injected here is new and reads the persistent cache that the
 * process saving a setting invalidates.
 */
final readonly class ApplySettingsOverlayBeforeJob
{
    public function __construct(
        private DatabaseConfigOverlay $overlay,
        private PerModelSettingResolver $settings,
    ) {}

    public function handle(JobProcessing $event): void
    {
        $this->overlay->applyFromDatabase($this->settings);
    }
}
