<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait HasTranslationsFactory
{
    public function createTranslations(Model $content, ?callable $callback = null): void
    {
        $default_locale = config('app.locale');
        $all_locales = config('app.available_locales', translations());

        $locales_to_duplicate = collect($all_locales)
            ->filter(fn (string $locale): bool => $locale !== $default_locale)
            ->values();

        if ($locales_to_duplicate->isEmpty()) {
            return;
        }

        $default_translation = $content->translations()->where('locale', $default_locale)->first();

        throw_unless($default_translation, RuntimeException::class, 'Default translation not found for locale: ' . $default_locale);

        $fields = $content::getTranslatableFields();

        // Always create at least 1 extra translation (when possible) to keep tests deterministic.
        $extra_count = fake()->numberBetween(1, $locales_to_duplicate->count());
        $locales_to_create = $locales_to_duplicate
            ->shuffle()
            ->take($extra_count)
            ->values()
            ->toArray();

        foreach ($locales_to_create as $locale) {
            $data = [];

            foreach ($fields as $field) {
                $value = $default_translation->{$field};

                if ($field === 'components' && $value === null) {
                    $value = [];
                }

                $data[$field] = $value;
            }

            // Allow callers to override specific fields (e.g. title) for each locale.
            if ($callback) {
                $data = array_merge($data, $callback($locale));
            }

            $content->setTranslation($locale, $data);
        }
    }
}
