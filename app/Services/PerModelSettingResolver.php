<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Models\Setting;

/**
 * Singleton that loads settings from the database once per lifecycle.
 *
 * The first lookup reads the persistent cache (or hydrates it from the DB);
 * further lookups reuse the in-memory collection until {@see flush()}.
 */
final class PerModelSettingResolver
{
    /**
     * @var Collection<string, Setting>|null
     */
    private ?Collection $loaded_settings = null;

    /**
     * Resolve a boolean setting by name.
     * When no row exists, returns {@see $default}.
     */
    public function boolean(string $name, bool $default): bool
    {
        $stored = $this->settings()->get($name)?->value;

        if ($stored === null) {
            return $default;
        }

        return (bool) $stored;
    }

    /**
     * Drop persistent and in-memory caches so the next lookup reloads from the database.
     */
    public function flush(): void
    {
        Cache::forget(self::cacheKey());
        $this->loaded_settings = null;
    }

    private static function cacheKey(): string
    {
        return CacheManager::key('settings', 'by_name');
    }

    /**
     * @return Collection<string, Setting>
     */
    private function settings(): Collection
    {
        if ($this->loaded_settings !== null) {
            return $this->loaded_settings;
        }

        $this->loaded_settings = Cache::rememberForever(
            self::cacheKey(),
            static fn (): Collection => Setting::query()
                ->get()
                ->keyBy('name'),
        );

        return $this->loaded_settings;
    }
}
