<?php

declare(strict_types=1);

namespace Modules\Core\Services\Translation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class TranslationCatalogService
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly ?\Closure $languagesProvider = null,
    ) {
    }

    /**
        * @return array<string,mixed>
        */
    public function buildTranslations(?string $lang, string $defaultLocale): array
    {
        $languages = $this->getLanguages($defaultLocale);
        $translations = [];

        foreach ($languages as $language) {
            $shortName = explode(DIRECTORY_SEPARATOR, $language);
            $shortName = array_pop($shortName);

            if ($lang && $shortName !== $lang) {
                continue;
            }

            $translations[$shortName] = $this->mergeLanguageFiles($language);

            if ($shortName !== $defaultLocale && array_key_exists($defaultLocale, $translations)) {
                $translations[$shortName] = array_merge($translations[$defaultLocale], $translations[$shortName]);
            }
        }

        if (! in_array($lang, [null, '', '0'], true)) {
            return head($translations);
        }

        return $translations;
    }

    /**
     * @return array<int,string>
     */
    public function getLanguages(string $defaultLocale): array
    {
        $languages = $this->languagesProvider ? ($this->languagesProvider)() : translations(true, true);

        usort($languages, function ($a, $b) use ($defaultLocale): int {
            if (Str::endsWith($a, DIRECTORY_SEPARATOR . $defaultLocale)) {
                return -1;
            }

            if (Str::endsWith($b, DIRECTORY_SEPARATOR . $defaultLocale)) {
                return 1;
            }

            return $a <=> $b;
        });

        return $languages;
    }

    /**
     * @return array<string,mixed>
     */
    public function mergeLanguageFiles(string $language): array
    {
        $translations = [];

        /** @var array<int,string> $files */
        $files = glob($language . '/*.php');

        foreach ($files as $file) {
            $contents = include $file;
            $translations[basename($file, '.php')] = $contents;
        }

        return Arr::dot($translations);
    }
}

