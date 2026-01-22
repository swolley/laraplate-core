<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

final readonly class FlagCDNService
{
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
        $flags_dir = public_path('flags');
        $flag_file = sprintf('%s/%s_%dx%d.%s', $flags_dir, $locale, $width, $height, $format);
        $flag_url = sprintf('/flags/%s_%dx%d.%s', $locale, $width, $height, $format);

        // Create flags directory if it doesn't exist
        if (! File::isDirectory($flags_dir)) {
            File::makeDirectory($flags_dir, 0755, true);
        }

        // If flag already exists locally, return local URL
        if (File::exists($flag_file)) {
            return $flag_url;
        }

        // Try to download from flagcdn
        $flagcdn_url = sprintf('https://flagcdn.com/%dx%d/%s.%s', $width, $height, $locale, $format);

        try {
            $response = Http::timeout(5)->get($flagcdn_url);

            if ($response->successful()) {
                File::put($flag_file, $response->body());

                return $flag_url;
            }
        } catch (Exception $e) {
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
        $flags_dir = public_path('flags');
        $flag_file = sprintf('%s/%s_%dx%d.%s', $flags_dir, $locale, $width, $height, $format);
        $flagcdn_url = sprintf('https://flagcdn.com/%dx%d/%s.%s', $width, $height, $locale, $format);

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
        } catch (Exception $e) {
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
