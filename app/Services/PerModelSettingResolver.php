<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Models\Setting;

/**
 * Singleton gateway for reading rows from the {@see Setting} table.
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
     * @return Collection<string, Setting>
     */
    public function collection(): Collection
    {
        return $this->settings();
    }

    /**
     * @return Collection<string, Setting>
     */
    public function group(string $group_name): Collection
    {
        return $this->settings()->filter(
            static fn (Setting $setting): bool => $setting->group_name === $group_name,
        );
    }

    /**
     * Resolve a setting value by name.
     * When no row exists, returns {@see $default}.
     */
    public function value(string $name, mixed $default = null): mixed
    {
        $setting = $this->settings()->get($name);

        if ($setting === null) {
            return $default;
        }

        return $setting->value ?? $default;
    }

    /**
     * Resolve a boolean setting by name.
     * When no row exists, returns {@see $default}.
     */
    public function boolean(string $name, bool $default): bool
    {
        $stored = $this->value($name, null);

        if ($stored === null) {
            return $default;
        }

        return (bool) $stored;
    }

    public function int(string $name, int $default): int
    {
        $stored = $this->value($name, null);

        if ($stored === null) {
            return $default;
        }

        return (int) $stored;
    }

    public function float(string $name, float $default): float
    {
        $stored = $this->value($name, null);

        if ($stored === null) {
            return $default;
        }

        return (float) $stored;
    }

    public function string(string $name, string $default): string
    {
        $stored = $this->value($name, null);

        if ($stored === null) {
            return $default;
        }

        return (string) $stored;
    }

    /**
     * Drop persistent and in-memory caches so the next lookup reloads from the database.
     */
    public function flush(): void
    {
        Cache::forget(self::cacheKey());
        $this->loaded_settings = null;
    }

    public static function cacheKey(): string
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
