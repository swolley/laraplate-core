<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Throwable;

/**
 * Turns the public CRUD API on for the duration of a callback by writing the
 * database setting the per-request overlay reads.
 *
 * {@see config(['core.expose_crud_api' => true])} is not enough: ApplyDatabaseSettingsOverlay
 * recopies every dotted setting from the DB onto the config repository at the start of
 * each HTTP request, so a process-level flip is discarded. Callers that need the API
 * open (perf:crud, Feature tests) go through here instead.
 */
final class CrudApiExposure
{
    private const string SETTING_NAME = 'core.expose_crud_api';

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     *
     * @throws Throwable
     */
    public static function runEnabled(callable $callback): mixed
    {
        $existing = Setting::query()->where('name', self::SETTING_NAME)->first();
        $previous = $existing?->value;
        $created = $existing === null;

        self::write(true);

        try {
            return $callback();
        } finally {
            if ($created) {
                Setting::query()->where('name', self::SETTING_NAME)->delete();
                self::flushCaches();
            } else {
                self::write($previous);
            }
        }
    }

    /**
     * Enable the API for the rest of this process without restoring afterwards.
     *
     * Use only inside a DB transaction that will roll back (e.g. the transient
     * superadmin path of perf:crud); otherwise prefer {@see runEnabled()}.
     */
    public static function enable(): void
    {
        self::write(true);
    }

    private static function write(mixed $value): void
    {
        $setting = Setting::query()->where('name', self::SETTING_NAME)->first();

        if ($setting instanceof Setting) {
            $setting->setSkipValidation(true);
            $setting->setForcedApprovalUpdate(true);
            $setting->forceFill(['value' => $value])->save();
        } else {
            Setting::factory()->persistedWithoutApprovalCapture()->create([
                'name' => self::SETTING_NAME,
                'value' => $value,
                'type' => SettingTypeEnum::Boolean,
                'group_name' => 'core',
                'description' => 'Expose CRUD API endpoints',
            ]);
        }

        self::flushCaches();
    }

    private static function flushCaches(): void
    {
        if (app()->bound(PerModelSettingResolver::class)) {
            app(PerModelSettingResolver::class)->flush();
        }

        if (app()->bound(SettingsCacheCoordinator::class)) {
            app(SettingsCacheCoordinator::class)->flushAll();
        }
    }
}
