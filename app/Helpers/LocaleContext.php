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
     * The configured values the cached default was resolved from: when the setting or the installation default
     * changes, the default is resolved again.
     *
     * @var array{0: mixed, 1: mixed}|null
     */
    private static ?array $cached_default_source = null;

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
     * The locale translations fall back to: the `default_language` setting (`core.default_language`) when it
     * names an available locale, else the installation default `app.default_locale` (APP_LOCALE). Not
     * app.locale, which is the language of the current request: App::setLocale() rewrites it, and the
     * localization middleware calls it for every user whose language is not the default one.
     *
     * Resolved once per configured value: checking availability scans the lang directories, and this runs on
     * every translated query.
     */
    public static function getDefault(): string
    {
        $source = [config('core.default_language'), config('app.default_locale')];

        if (self::$cached_default_locale === null || self::$cached_default_source !== $source) {
            self::$cached_default_source = $source;
            self::$cached_default_locale = self::resolveDefault($source[0], $source[1]);
        }

        return self::$cached_default_locale;
    }

    /**
     * Get default locale from config, cached once per configured value.
     */
    public static function getDefaultCached(): string
    {
        return self::getDefault();
    }

    /**
     * Reset the cached default locale (tests and long-running workers).
     */
    public static function resetDefaultLocaleCache(): void
    {
        self::$cached_default_locale = null;
        self::$cached_default_source = null;
    }

    private static function resolveDefault(mixed $configured, mixed $installed): string
    {
        $installed = is_string($installed) && $installed !== '' ? $installed : (string) config('app.fallback_locale', 'en');

        if (! is_string($configured) || $configured === '' || $configured === $installed) {
            return $installed;
        }

        $available = config('app.available_locales', translations());

        return is_array($available) && in_array($configured, $available, true) ? $configured : $installed;
    }
}
