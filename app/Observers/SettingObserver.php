<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;

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
        if ($setting->group_name === 'versioning' || $setting->getOriginal('group_name') === 'versioning') {
            Cache::forget(CacheManager::key('version_strategies'));
        }

        app(PerModelSettingResolver::class)->flush();
    }

    public function deleted(Setting $setting): void
    {
        if ($setting->group_name === 'versioning') {
            Cache::forget(CacheManager::key('version_strategies'));
        }

        app(PerModelSettingResolver::class)->flush();
    }
}
