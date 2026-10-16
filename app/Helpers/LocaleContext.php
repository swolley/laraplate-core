<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Support\Facades\App;

final class LocaleContext
{
    /**
     * Cached default locale for hot paths (e.g. HasTranslations fallback).
     * Reset in tests via resetDefaultLocaleCache().
     */
    private static ?string $cached_default_locale = null;

    /**
     * The locale translated content is read and written in: the content locale the reader picked, when there
     * is one, else the language of the request.
     */
    public static function get(): string
    {
        return self::getContentLocale() ?? (App::getLocale() ?: self::getDefault());
    }

    /**
     * Switches the locale translated content is read and written in. While a content locale is picked, only
     * that one moves, so the interface keeps the language of the request.
     */
    public static function set(string $locale): void
    {
        if (self::getContentLocale() !== null) {
            self::setContentLocale($locale);

            return;
        }

        App::setLocale($locale);
    }

    /**
     * The content locale the reader picked for this request, apart from the language of the interface (the
     * backoffice does), or null when content follows the language of the request.
     */
    public static function getContentLocale(): ?string
    {
        $locale = app(ContentLocale::class)->locale;

        return $locale !== null && $locale !== '' ? $locale : null;
    }

    public static function setContentLocale(?string $locale): void
    {
        app(ContentLocale::class)->locale = $locale;
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
        return $locale === self::getDefault();
    }

    /**
     * The locale translations fall back to. Not app.locale, which is the language of the current request:
     * App::setLocale() rewrites it, and the localization middleware calls it for every user whose language
     * is not the default one.
     */
    public static function getDefault(): string
    {
        $locale = config('app.default_locale');

        return is_string($locale) && $locale !== '' ? $locale : (string) config('app.fallback_locale', 'en');
    }

    /**
     * Get default locale from config, cached once per request/process.
     */
    public static function getDefaultCached(): string
    {
        if (self::$cached_default_locale === null) {
            self::$cached_default_locale = self::getDefault();
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
