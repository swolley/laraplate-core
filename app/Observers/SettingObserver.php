<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

final class SettingObserver
{
    public function updated(Setting $setting): void
    {
        if ($setting->group_name === 'versioning') {
            Cache::forget('version_strategies');
        }
    }
}
