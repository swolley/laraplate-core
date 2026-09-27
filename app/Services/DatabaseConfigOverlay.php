<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Contracts\Config\Repository;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\Setting;
use Throwable;

/**
 * Copies database settings into the runtime config repository.
 *
 * A setting name carries no module prefix: the declaring module lives in its own column, and the
 * config key is `{module}.{name}` (e.g. module `Core` + `auth.enable_user_2fa` is read as
 * `core.auth.enable_user_2fa`). Rows without a module are not overlaid.
 */
final readonly class DatabaseConfigOverlay
{
    public function __construct(
        private Repository $config,
    ) {}

    public static function configKey(?string $module, string $name): ?string
    {
        if ($module === null || $module === '' || $name === '') {
            return null;
        }

        return mb_strtolower($module) . '.' . $name;
    }

    public function applyFromDatabase(PerModelSettingResolver $settings): void
    {
        try {
            $setting = new Setting;

            // SchemaInspector, not getSchemaBuilder(): the inspector memoizes the
            // table probe for the process, so the overlay does not pay an
            // information_schema round-trip on every HTTP request.
            if (! SchemaInspector::getInstance()->hasTable(
                $setting->getTable(),
                $setting->getConnectionName(),
            )) {
                return;
            }

            $this->applySettings($settings->collection());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  iterable<int, object>  $settings
     */
    public function applySettings(iterable $settings): void
    {
        foreach ($settings as $setting) {
            $module = data_get($setting, 'module');
            $key = self::configKey(is_string($module) ? $module : null, (string) data_get($setting, 'name'));

            if ($key === null) {
                continue;
            }

            $this->config->set($key, data_get($setting, 'value'));
        }
    }

    /**
     * Sync a single setting onto the in-memory config repository for the current request.
     */
    public function applySetting(Setting $setting): void
    {
        $key = self::configKey($setting->module, $setting->name);

        if ($key === null) {
            return;
        }

        $this->config->set($key, $setting->value);
    }
}
