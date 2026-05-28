<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Modules\Core\Models\Setting;
use Modules\Core\Services\SettingsCacheCoordinator;

final class SettingObserver
{
    public function saving(Setting $setting): void
    {
        if ($setting->value === '') {
            $setting->value = null;
        }
    }

    public function saved(Setting $setting): void
    {
        app(SettingsCacheCoordinator::class)->flushSetting($setting, sync_runtime_config: true);
    }

    public function deleted(Setting $setting): void
    {
        app(SettingsCacheCoordinator::class)->flushSetting($setting);
    }
}
