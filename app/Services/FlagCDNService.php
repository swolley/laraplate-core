<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

final readonly class FlagCDNService
{
    /**
     * Map locale codes to FlagCDN country codes.
     *
     * The mapping is defined in module lang files:
     * - `Modules/Core/lang/{language}/app.php` can expose `flag => 'gb'` (or any other FlagCDN code)
     * - if missing, we fallback to the base language code (e.g. `de`, `it`, ...)
     */
    private function mapLocaleToFlagLocale(string $locale): string
    {
        $normalized = strtolower(trim($locale));
        $language_code = strtolower(strtok(str_replace('_', '-', $normalized), '-')) ?: $normalized;

        static $cache = [];
        if (isset($cache[$language_code])) {
            return $cache[$language_code];
        }

        $module_root = dirname(__DIR__, 2);
        $lang_file = sprintf('%s/lang/%s/app.php', $module_root, $language_code);

        $flag_locale = null;

        if (is_file($lang_file)) {
            /** @var array<string,mixed> $translations */
            $translations = require $lang_file;

            $candidate = $translations['flag'] ?? null;
            if (is_string($candidate)) {
                $candidate = strtolower(trim($candidate));

                if ($candidate !== '') {
                    $flag_locale = $candidate;
                }
            }
        }

        return $cache[$language_code] = $flag_locale ?? $language_code;
    }

    /**
     * Get flag URL for a locale, downloading and caching it locally if needed.
     *
     * @param  string  $locale  The locale code (e.g., 'it', 'en')
     * @param  int  $width  Flag width in pixels (default: 40)
     * @param  int  $height  Flag height in pixels (default: 30)
     * @param  string  $format  Image format: 'png' or 'webp' (default: 'png')
     * @return string The URL to the flag image
     */
    public function getUrl(string $locale, int $width = 40, int $height = 30, string $format = 'png'): string
    {
        $flag_locale = $this->mapLocaleToFlagLocale($locale);
        $flags_dir = public_path('flags');
        $flag_file = sprintf('%s/%s_%dx%d.%s', $flags_dir, $flag_locale, $width, $height, $format);
        $flag_url = sprintf('/flags/%s_%dx%d.%s', $flag_locale, $width, $height, $format);

        // Create flags directory if it doesn't exist
        if (! File::isDirectory($flags_dir)) {
            File::makeDirectory($flags_dir, 0755, true);
        }

        // If flag already exists locally, return local URL
        if (File::exists($flag_file)) {
            return $flag_url;
        }

        // Try to download from flagcdn
        $flagcdn_url = sprintf('https://flagcdn.com/%dx%d/%s.%s', $width, $height, $flag_locale, $format);

        try {
            $response = Http::timeout(5)->get($flagcdn_url);

            if ($response->successful()) {
                File::put($flag_file, $response->body());

                return $flag_url;
            }
        } catch (Exception) {
            // If download fails, fallback to flagcdn URL
            return $flagcdn_url;
        }

        // Fallback to flagcdn URL if file doesn't exist and download failed
        return $flagcdn_url;
    }

    /**
     * Download a flag for a specific locale, size and format.
     *
     * @param  string  $locale  The locale code
     * @param  int  $width  Flag width in pixels
     * @param  int  $height  Flag height in pixels
     * @param  string  $format  Image format: 'png' or 'webp'
     * @return bool True if download was successful, false otherwise
     */
    public function download(string $locale, int $width, int $height, string $format): bool
    {
        $flag_locale = $this->mapLocaleToFlagLocale($locale);
        $flags_dir = public_path('flags');
        $flag_file = sprintf('%s/%s_%dx%d.%s', $flags_dir, $flag_locale, $width, $height, $format);
        $flagcdn_url = sprintf('https://flagcdn.com/%dx%d/%s.%s', $width, $height, $flag_locale, $format);

        // Create flags directory if it doesn't exist
        if (! File::isDirectory($flags_dir)) {
            File::makeDirectory($flags_dir, 0755, true);
        }

        // Skip if already exists
        if (File::exists($flag_file)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get($flagcdn_url);

            if ($response->successful()) {
                File::put($flag_file, $response->body());

                return true;
            }
        } catch (Exception) {
            return false;
        }

        return false;
    }

    /**
     * Get the flags directory path.
     */
    public function getFlagsDirectory(): string
    {
        return public_path('flags');
    }
}
