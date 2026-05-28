<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Contracts\Config\Repository;
use Modules\Core\Models\Setting;
use Throwable;

/**
 * Copies database settings whose names use Laravel dot notation into the runtime config repository.
 *
 * Application settings (e.g. default_language, version_strategy_core_users) stay resolver-only
 * and are excluded because they do not contain a dot.
 */
final readonly class DatabaseConfigOverlay
{
    public function __construct(
        private Repository $config,
    ) {}

    public static function shouldOverlay(string $name): bool
    {
        return $name !== '' && str_contains($name, '.');
    }

    public function applyFromDatabase(PerModelSettingResolver $settings): void
    {
        try {
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
            $name = (string) data_get($setting, 'name');

            if (! self::shouldOverlay($name)) {
                continue;
            }

            $this->config->set($name, data_get($setting, 'value'));
        }
    }

    /**
     * Sync a single setting onto the in-memory config repository for the current request.
     */
    public function applySetting(Setting $setting): void
    {
        if (! self::shouldOverlay($setting->name)) {
            return;
        }

        $this->config->set($setting->name, $setting->value);
    }
}
