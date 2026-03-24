<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Models\Setting;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Override;
use Throwable;

final class ModuleDatabaseActivator implements ActivatorInterface
{
    /**
     * Table name for Setting model (used in checkSettingTable without loading Eloquent).
     */
    private const string SETTING_TABLE = 'settings';

    public static string $RECORD_NAME = 'backendModules';

    /**
     * @var class-string
     */
    public static string $MODEL_NAME = Setting::class;

    private readonly string $cacheKey;

    private readonly int $cacheLifetime;

    private readonly \Illuminate\Contracts\Config\Repository $configs;

    private array $modulesStatuses;

    public function __construct(Container $app)
    {
        $this->configs = $app->make(\Illuminate\Contracts\Config\Repository::class);
        $this->cacheKey = $this->config('cache-key', 'modules_db_activator_statuses');
        $this->cacheLifetime = (int) $this->config('cache-lifetime', 3600);
        $this->modulesStatuses = $this->getModulesStatuses();

        self::checkSettingTable();
    }

    /**
     * Checks whether the settings table exists so the database activator can be used.
     * Uses only the DatabaseManager (Schema) so it works before Eloquent's connection
     * resolver is set. The backendModules record is created on first read by ensureBackendModulesRecord().
     */
    public static function checkSettingTable(): bool
    {
        try {
            $cache_key = 'modules_db_activator_checked';

            if (Cache::has($cache_key)) {
                return Cache::get($cache_key);
            }

            if (! app()->bound('db')) {
                return false;
            }

            if (! Schema::hasTable(self::SETTING_TABLE)) {
                return false;
            }

            Cache::put($cache_key, true);

            return true;
        } catch (Throwable $e) {
            Log::error("Error checking modules db activator: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * @return array<int,string>
     *
     * @psalm-return list<string>
     */
    public static function getAllModulesNames(): array
    {
        $modules = glob(config('modules.paths.modules') . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $parsed_names = [];

        foreach ($modules as $path) {
            $parsed_names[] = Str::afterLast($path, DIRECTORY_SEPARATOR);
        }

        return $parsed_names;
    }

    /**
     * Creates or updates the backendModules record (uses Eloquent; call only when app is booted).
     */
    public static function seedBackendModules(): Setting
    {
        $model = self::$MODEL_NAME;
        $found = $model::where('name', self::$RECORD_NAME)->first();
        $all_modules = self::getAllModulesNames();

        if (! $found) {
            $found = $model::create([
                'value' => $all_modules,
                'choices' => $all_modules,
                'name' => self::$RECORD_NAME,
                'type' => 'json',
                'group_name' => 'backend',
                'description' => 'backend modules',
            ]);
        } elseif (sort($found->choices) !== sort($all_modules)) {
            $found->update(['choices' => $all_modules]);
        }

        return $found;
    }

    #[Override]
    public function reset(): void
    {
        $this->updateRecordValue(self::getAllModulesNames());
        $this->flushCache();
    }

    #[Override]
    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    #[Override]
    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    #[Override]
    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        if (! in_array($name, $this->modulesStatuses, true)) {
            return $status === false;
        }

        return ($status && in_array($name, $this->modulesStatuses, true)) || (! $status && ! in_array($name, $this->modulesStatuses, true));
    }

    #[Override]
    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    #[Override]
    public function setActiveByName(string $name, bool $active): void
    {
        if ($active && ! in_array($name, $this->modulesStatuses, true)) {
            $this->modulesStatuses[] = $name;
            $this->updateRecordValue($this->modulesStatuses);
            $this->flushCache();
        } elseif (! $active && in_array($name, $this->modulesStatuses, true)) {
            $this->modulesStatuses = array_values(array_filter($this->modulesStatuses, fn (string $m): bool => $m !== $name));
            $this->updateRecordValue($this->modulesStatuses);
            $this->flushCache();
        }
    }

    #[Override]
    public function delete(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    /**
     * Ensures the backendModules row exists (query builder only, no Eloquent).
     */
    private function ensureBackendModulesRecord(): void
    {
        $exists = DB::table(self::SETTING_TABLE)
            ->where('name', self::$RECORD_NAME)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $all_modules = self::getAllModulesNames();
        $now = now();

        DB::table(self::SETTING_TABLE)->insertOrIgnore([
            'name' => self::$RECORD_NAME,
            'value' => json_encode($all_modules),
            'choices' => json_encode($all_modules),
            'encrypted' => false,
            'type' => 'json',
            'group_name' => 'modules',
            'description' => 'application modules',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Log::info("Created settings '{name}' config record", ['name' => self::$RECORD_NAME]);
    }

    private function updateRecordValue(array $value): void
    {
        DB::table(self::SETTING_TABLE)
            ->where('name', self::$RECORD_NAME)
            ->whereNull('deleted_at')
            ->update([
                'value' => json_encode($value),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function readSettings(): array
    {
        $raw = DB::table(self::SETTING_TABLE)
            ->where('name', self::$RECORD_NAME)
            ->whereNull('deleted_at')
            ->value('value');

        if ($raw === null) {
            $this->ensureBackendModulesRecord();

            return self::getAllModulesNames();
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    private function getModulesStatuses(): array
    {
        if (! $this->configs->get('modules.cache.enabled')) {
            return $this->readSettings();
        }

        $driver = $this->configs->get('modules.cache.driver');

        return Cache::store($driver)->remember($this->cacheKey, $this->cacheLifetime, $this->readSettings(...));
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->configs->get('modules.activators.database.' . $key, $default);
    }

    private function flushCache(): void
    {
        $driver = $this->configs->get('modules.cache.driver');
        Cache::store($driver)->forget($this->cacheKey);
    }
}
