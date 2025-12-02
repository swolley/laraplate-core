<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\App;

final class LocaleContext
{
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
     * Check if translation fallback is enabled.
     */
    public static function isFallbackEnabled(): bool
    {
        return (bool) config('core.translation_fallback_enabled', config('app.translation_fallback_enabled', true));
    }
}
