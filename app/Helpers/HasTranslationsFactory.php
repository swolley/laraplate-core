<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Modules\Cms\Helpers\HasSlug;

trait HasTranslationsFactory
{
    public function createTranslations(Model $content, callable $callback): void
    {
        $default_locale = config('app.locale');
        $all_locales = translations();
        $locales_to_create = collect($all_locales)->inRandomOrder()->limit(fake()->numberBetween(0, count($all_locales)))->filter(fn (string $locale): bool => $locale !== $default_locale)->toArray();
        array_unshift($locales_to_create, $default_locale);

        $fields = $content::getTranslatableFields();
        $content->getTranslationModelClass()->casts();

        foreach ($locales_to_create as $locale) {
            $data = [];

            foreach ($fields as $field) {
                if ($field !== 'slug') {
                    $data[$field] = /*match ($casts[$field]) {
                        // 'array' => fake($locale)->paragraphs(fake()->rand(1, 10)),
                        // 'object' => (object) [
                        //     'text' => fake($locale)->text(fake()->numberBetween(100, 255)),
                        // ],
                        default =>*/ fake($locale)->text(fake()->numberBetween(100, 255))/*,
                     }*/;
                }
            }

            if (class_uses_trait($content, HasSlug::class)) {
                $data['slug'] = $content->generateSlug();
            }

            $data = array_merge($data, $callback($locale));
            $content->setTranslation($locale, $data);
        }
    }
}
