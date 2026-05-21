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
 * Group lookups read the persistent group cache (or hydrate it from the DB);
 * further lookups reuse in-memory group collections until flushed.
 */
final class PerModelSettingResolver
{
    /**
     * @var array<string, Collection<string, Setting>>
     */
    private array $loaded_groups = [];

    /**
     * @var array<string, string>|null
     */
    private ?array $name_index = null;

    /**
     * @return Collection<string, Setting>
     */
    public function collection(): Collection
    {
        $settings = new Collection();

        foreach (array_unique(array_values($this->nameIndex())) as $group_name) {
            $settings = $settings->merge($this->group($group_name));
        }

        return $settings;
    }

    /**
     * @return Collection<string, Setting>
     */
    public function group(string $group_name): Collection
    {
        return $this->loaded_groups[$group_name] ??= Cache::rememberForever(
            self::groupCacheKey($group_name),
            static fn (): Collection => Setting::query()
                ->where('group_name', $group_name)
                ->get()
                ->keyBy('name'),
        );
    }

    /**
     * Resolve a setting value by name.
     * When no row exists, returns {@see $default}.
     */
    public function value(string $name, mixed $default = null): mixed
    {
        $group_name = $this->nameIndex()[$name] ?? null;

        if ($group_name === null) {
            return $default;
        }

        $setting = $this->group($group_name)->get($name);

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
        Cache::forget(self::legacyTableCacheKey());
        $this->flushNameIndex();

        foreach (array_keys($this->loaded_groups) as $group_name) {
            Cache::forget(self::groupCacheKey($group_name));
        }

        $this->loaded_groups = [];
    }

    public function flushGroup(string $group_name): void
    {
        Cache::forget(self::groupCacheKey($group_name));
        unset($this->loaded_groups[$group_name]);
    }

    /**
     * @param  array<int, string>  $group_names
     */
    public function flushGroups(array $group_names): void
    {
        foreach (array_unique(array_filter($group_names)) as $group_name) {
            $this->flushGroup((string) $group_name);
        }
    }

    public function flushNameIndex(): void
    {
        Cache::forget(self::nameIndexCacheKey());
        $this->name_index = null;
    }

    public static function cacheKey(): string
    {
        return CacheManager::key('settings', 'by_name');
    }

    public static function groupCacheKey(string $group_name): string
    {
        return CacheManager::key('settings', 'group', $group_name);
    }

    public static function nameIndexCacheKey(): string
    {
        return CacheManager::key('settings', 'name_index');
    }

    public static function legacyTableCacheKey(): string
    {
        return CacheManager::key('settings');
    }

    /**
     * @return array<string, string>
     */
    private function nameIndex(): array
    {
        if ($this->name_index !== null) {
            return $this->name_index;
        }

        $this->name_index = Cache::rememberForever(
            self::nameIndexCacheKey(),
            static fn (): array => Setting::query()
                ->pluck('group_name', 'name')
                ->toArray(),
        );

        return $this->name_index;
    }
}
