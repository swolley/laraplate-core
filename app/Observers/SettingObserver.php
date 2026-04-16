<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Setting;

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
        if ($setting->group_name === 'versioning' && $setting->getOriginal('group_name') === 'versioning') {
            Cache::forget('version_strategies');
        } elseif ($setting->group_name === 'soft_deletes' && $setting->getOriginal('group_name') === 'soft_deletes') {
            Cache::forget('soft_deletes_flags');
        }
    }
}
