<?php

declare(strict_types=1);

namespace Modules\Core\Services\Translation;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TranslationService implements TranslationServiceInterface
{
    private readonly TranslationServiceInterface $primary_service;

    private ?TranslationServiceInterface $fallback_service = null;

    private readonly bool $cache_enabled;

    public function __construct()
    {
        $provider = config('core.auto_translate_provider', 'deepl');
        $this->cache_enabled = config('core.translation_cache_enabled', true);

        // Initialize primary service
        $this->primary_service = match ($provider) {
            'deepl' => new DeepLTranslationService(),
            'ai' => new AiTranslationService(),
            default => throw new Exception("Unsupported translation provider: {$provider}"),
        };

        // Initialize fallback service if enabled
        if (config('core.auto_translate_fallback_to_ai', true) && $provider !== 'ai') {
            $this->fallback_service = new AiTranslationService();
        }
    }

    public function translate(string $text, string $from_locale, string $to_locale): string
    {
        if ($text === '' || $text === '0') {
            return $text;
        }

        // Check cache
        if ($this->cache_enabled) {
            $cache_key = $this->getCacheKey($text, $from_locale, $to_locale);
            $cached = Cache::get($cache_key);

            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $translated = $this->primary_service->translate($text, $from_locale, $to_locale);

            // Cache result
            if ($this->cache_enabled) {
                Cache::put($cache_key, $translated, now()->addDays(30));
            }

            return $translated;
        } catch (Exception $e) {
            Log::warning('Primary translation service failed, trying fallback', [
                'error' => $e->getMessage(),
                'from' => $from_locale,
                'to' => $to_locale,
            ]);

            // Try fallback service
            if ($this->fallback_service instanceof TranslationServiceInterface) {
                try {
                    $translated = $this->fallback_service->translate($text, $from_locale, $to_locale);

                    // Cache result
                    if ($this->cache_enabled) {
                        Cache::put($cache_key, $translated, now()->addDays(30));
                    }

                    return $translated;
                } catch (Exception $fallback_error) {
                    Log::error('Fallback translation service also failed', [
                        'error' => $fallback_error->getMessage(),
                        'from' => $from_locale,
                        'to' => $to_locale,
                    ]);
                }
            }

            // If all services fail, return original text
            return $text;
        }
    }

    public function translateBatch(array $texts, string $from_locale, string $to_locale): array
    {
        if ($texts === []) {
            return [];
        }

        $translations = [];

        foreach ($texts as $text) {
            $translations[] = $this->translate($text, $from_locale, $to_locale);
        }

        return $translations;
    }

    private function getCacheKey(string $text, string $from_locale, string $to_locale): string
    {
        return 'translation:' . md5($text . $from_locale . $to_locale);
    }
}
