<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use BadMethodCallException;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Models\Setting;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;
use Override;

final class ModuleDatabaseActivator implements ActivatorInterface
{
    public static string $RECORD_NAME = 'backendModules';

    /**
     * @var class-string
     */
    public static string $MODEL_NAME = Setting::class;

    private readonly \Modules\Core\Cache\Repository $cache;

    private readonly string $cacheKey;

    private readonly int $cacheLifetime;

    private readonly \Illuminate\Config\Repository $configs;

    private array $modulesStatuses;

    public function __construct(Container $app)
    {
        $this->cache = $app->make(\Modules\Core\Cache\Repository::class);
        // $this->files = $app['files'];
        $this->configs = $app->make(\Illuminate\Contracts\Config\Repository::class);
        // $this->statusesFile = $this->config('statuses-file');
        $this->cacheKey = $this->config('cache-key');
        $this->cacheLifetime = $this->config('cache-lifetime');
        $this->modulesStatuses = $this->getModulesStatuses();

        self::checkSettingTable();
    }

    public static function checkSettingTable(): bool
    {
        try {
            $model = self::$MODEL_NAME;

            throw_unless(class_exists($model), BadMethodCallException::class, 'No Setting model found in the application');

            throw_unless(Schema::hasTable(new $model()->getTable()), BadMethodCallException::class, 'No settings table found in the database schema');

            if (! Setting::query()->where('name', self::$RECORD_NAME)->exists()) {
                self::seedBackendModules();
                Log::info("Created Setting '{name}' config record", ['name' => self::$RECORD_NAME]);
            }

            return true;
        } catch (Exception) {
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

    public static function seedBackendModules(): Model
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
        /** @psalm-suppress UndefinedClass */
        $this->getQuery()->update(['value' => self::getAllModulesNames()]);
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
        $this->setActiveByName($module->getName(), true);
    }

    #[Override]
    public function hasStatus(Module $module, bool $status): bool
    {
        if (! in_array($module->getName(), $this->modulesStatuses, true)) {
            return $status === false;
        }

        return ($status && in_array($module->getName(), $this->modulesStatuses, true)) || (! $status && ! in_array($module->getName(), $this->modulesStatuses, true));
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
            $this->getQuery()->update(['value' => $this->modulesStatuses]);
            $this->flushCache();
        } elseif (! $active && in_array($name, $this->modulesStatuses, true)) {
            $this->getQuery()->update(['value' => array_filter($this->modulesStatuses, fn ($m): bool => $m !== $name)]);
            $this->flushCache();
        }
    }

    #[Override]
    public function delete(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    private function getQuery(): Builder
    {
        // static::checkSettingTable();
        $model = self::$MODEL_NAME;

        /** @var Builder $query */
        $query = $model::query();

        return $query->where('name', self::$RECORD_NAME);
    }

    private function readSettings(): array
    {
        try {
            return $this->getQuery()->sole()->value;

            /** @psalm-suppress UndefinedClass */
        } catch (ModelNotFoundException) {
            return self::seedBackendModules()->value;
        } catch (BadMethodCallException) {
            return self::getAllModulesNames();
        }
    }

    private function getModulesStatuses(): array
    {
        if (! $this->configs->get('modules.cache.enabled')) {
            return $this->readSettings();
        }

        return $this->cache->store($this->configs->get('modules.cache.driver'))->remember($this->cacheKey, $this->cacheLifetime, $this->readSettings(...));
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->configs->get('modules.activators.database.' . $key, $default);
    }

    private function flushCache(): void
    {
        $this->cache->store($this->configs->get('modules.cache.driver'))->forget($this->cacheKey);
    }
}
