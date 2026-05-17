<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\App;

final class LocaleContext
{
    /**
     * Cached config('app.locale') for hot paths (e.g. HasTranslations fallback).
     * Reset in tests via resetDefaultLocaleCache().
     */
    private static ?string $cached_default_locale = null;

    /**
     * Get current locale context.
     */
    public static function get(): string
    {
        return App::getLocale() ?: config('app.locale');
    }

    /**
     * Set locale context.
     */
    public static function set(string $locale): void
    {
        App::setLocale($locale);
    }

    /**
     * Get available locales.
     */
    public static function getAvailable(): array
    {
        return translations();
    }

    /**
     * Check if locale is default.
     */
    public static function isDefault(string $locale): bool
    {
        return $locale === config('app.locale');
    }

    /**
     * Get default locale.
     */
    public static function getDefault(): string
    {
        return config('app.locale');
    }

    /**
     * Get default locale from config, cached once per request/process.
     */
    public static function getDefaultCached(): string
    {
        if (self::$cached_default_locale === null) {
            self::$cached_default_locale = (string) config('app.locale');
        }

        return self::$cached_default_locale;
    }

    /**
     * Reset the cached default locale (tests and long-running workers).
     */
    public static function resetDefaultLocaleCache(): void
    {
        self::$cached_default_locale = null;
    }

}
