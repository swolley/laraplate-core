<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\IProvidesRerankerText;

/**
 * {@see FusionFixtureSearchModel} whose hits carry no title, as a model with translated text does: the
 * titles come from {@see self::rerankerTexts()} instead, for the language the search asks.
 */
final class FusionFixtureTranslatedSearchModel extends Model implements IProvidesRerankerText
{
    /**
     * @var list<string>
     */
    public static array $requestedLocales = [];

    /**
     * When false the model has no text for any document.
     */
    public static bool $hasText = true;

    protected $guarded = [];

    /**
     * @param  string  $query
     * @param  callable|null  $callback
     */
    public static function search($query = '', $callback = null): FusionFixtureSearchBuilder
    {
        return new FusionFixtureSearchBuilder(new self(), (string) $query);
    }

    public static function rerankerTexts(array $keys, string $locale): array
    {
        self::$requestedLocales[] = $locale;

        if (! self::$hasText) {
            return [];
        }

        $titles = [];

        foreach (FusionFixtureSearchBuilder::RANKINGS as $rows) {
            foreach ($rows as $row) {
                $titles[$row['id']] = $row['title'];
            }
        }

        $texts = [];

        foreach ($keys as $key) {
            if (isset($titles[(int) $key])) {
                $texts[$key] = $titles[(int) $key];
            }
        }

        return $texts;
    }

    public static function reset(): void
    {
        self::$requestedLocales = [];
        self::$hasText = true;
    }

    public function searchableUsing(): FusionFixtureEngineName
    {
        return new FusionFixtureEngineName();
    }
}
