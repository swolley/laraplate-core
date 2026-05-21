<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Models\Setting;

/**
 * Central invalidation for all settings-related caches.
 *
 * Call {@see flushAll()} from {@see \Modules\Core\Observers\SettingObserver} and any
 * code that bulk-updates settings outside Eloquent events.
 */
final class SettingsCacheCoordinator
{
    /**
     * @var array<int, callable(): void>
     */
    private array $invalidators = [];

    /**
     * Register an extra invalidator (e.g. module-specific derived caches).
     */
    public function registerInvalidator(callable $invalidator): void
    {
        $this->invalidators[] = $invalidator;
    }

    /**
     * Drop persistent and in-memory settings caches across the application.
     */
    public function flushAll(): void
    {
        if (app()->bound(PerModelSettingResolver::class)) {
            app(PerModelSettingResolver::class)->flush();
        }

        // Legacy key used by older HasVersions builds; safe to forget until fully removed.
        Cache::forget(CacheManager::key('version_strategies'));

        HasVersions::resetVersionStrategyCache();

        (new Setting)->invalidateCache();

        foreach ($this->invalidators as $invalidator) {
            $invalidator();
        }
    }
}
