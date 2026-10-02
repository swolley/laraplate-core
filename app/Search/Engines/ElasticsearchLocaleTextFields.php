<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Services\ElasticsearchService;
use Throwable;

/**
 * The fields a text query searches when the results are requested in a language.
 *
 * Vectors carry no language, but text does: `title.it` is analysed as Italian and `title.en` as English, so a
 * request in Italian searches the Italian field of every per-language object and never the English one. Fields
 * with no language (keyword and text values such as `entity` or `preset`) stay. The mapping read is the live
 * one, because the component fields (`subtitle`, `content`, ...) are mapped dynamically, as an object with a
 * sub-field per language; the model's declared mapping is the fallback when the cluster cannot be asked.
 */
final readonly class ElasticsearchLocaleTextFields
{
    private const int CACHE_SECONDS = 300;

    private const string TITLE_BOOST = '^2';

    /**
     * @param  (Closure(string): (array<string, mixed>|null))|null  $liveProperties  Replaces the cluster, for tests.
     */
    public function __construct(private ?Closure $liveProperties = null) {}

    /**
     * Nested fields are left out (a plain multi_match cannot reach them), and so are the dates, numbers,
     * booleans and vectors, which are not text, and `locales`, which would make the word "it" match every
     * document available in Italian.
     *
     * @param  array<string, mixed>  $properties  The `properties` of an Elasticsearch mapping.
     * @param  list<string>  $locales  The languages the results are requested in.
     * @param  list<string>  $available  Every language the application has, to tell an object per language from any other.
     * @return list<string>
     */
    public static function fromProperties(array $properties, array $locales, array $available): array
    {
        $fields = [];
        self::collect($properties, '', $locales, $available, $fields);

        return array_values(array_unique($fields));
    }

    /**
     * @param  list<string>  $locales
     * @return list<string> The fields of those languages, or none when no mapping is known.
     */
    public function forModel(Model $model, string $index, array $locales): array
    {
        $properties = $this->live($index) ?? $this->declared($model);

        if ($properties === []) {
            return [];
        }

        return self::fromProperties($properties, $locales, LocaleContext::getAvailable());
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $locales
     * @param  list<string>  $available
     * @param  list<string>  $fields
     */
    private static function collect(array $properties, string $prefix, array $locales, array $available, array &$fields): void
    {
        foreach ($properties as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }

            if ($prefix === '' && $name === 'locales') {
                continue;
            }

            $path = $prefix . $name;
            $type = $definition['type'] ?? 'object';
            $children = $definition['properties'] ?? null;

            if (is_array($children)) {
                if ($type === 'nested') {
                    continue;
                }

                if (array_intersect(array_keys($children), $available) !== []) {
                    foreach ($locales as $locale) {
                        $sub = $children[$locale] ?? null;

                        if (is_array($sub) && in_array($sub['type'] ?? null, ['text', 'keyword'], true)) {
                            $fields[] = $path . '.' . $locale . ($path === 'title' ? self::TITLE_BOOST : '');
                        }
                    }

                    continue;
                }

                self::collect($children, $path . '.', $locales, $available, $fields);

                continue;
            }

            if (in_array($type, ['text', 'keyword'], true)) {
                $fields[] = $path;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function live(string $index): ?array
    {
        if ($this->liveProperties instanceof Closure) {
            return ($this->liveProperties)($index);
        }

        try {
            return Cache::remember('search:es-mapping:' . $index, self::CACHE_SECONDS, static function () use ($index): ?array {
                $mapping = ElasticsearchService::getInstance()->client->indices()->getMapping(['index' => $index])->asArray();
                $properties = (array_values($mapping)[0] ?? [])['mappings']['properties'] ?? null;

                return is_array($properties) ? $properties : null;
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function declared(Model $model): array
    {
        if (! method_exists($model, 'getSearchMapping')) {
            return [];
        }

        $mapping = $model->getSearchMapping();
        $properties = is_array($mapping) ? ($mapping['mappings']['properties'] ?? null) : null;

        return is_array($properties) ? $properties : [];
    }
}
