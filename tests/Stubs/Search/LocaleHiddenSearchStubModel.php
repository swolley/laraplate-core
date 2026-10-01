<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Models\Concerns\HasTranslations;
use Modules\Core\Search\Traits\Searchable;

/**
 * Searchable, translatable model carrying the real `LocaleScope`, so a row with no
 * translation in the current locale is hidden from `query()`. Indexing is recorded
 * instead of sent to an engine, to assert which rows a reindex reaches.
 */
class LocaleHiddenSearchStubModel extends Model implements ISearchableModel
{
    use HasTranslations;
    use Searchable;

    /**
     * @var list<int|string>
     */
    public static array $indexed = [];

    public $timestamps = false;

    protected $table = 'core_test_locale_hidden_search_stub';

    protected $guarded = [];

    public function queueMakeSearchable($models): void
    {
        foreach ($models as $model) {
            self::$indexed[] = $model->getKey();
        }
    }

    /**
     * @return class-string<Model&\Modules\Core\Services\Translation\Definitions\ITranslated>
     */
    protected static function getTranslationModelClass(): string
    {
        return LocaleHiddenSearchStubTranslation::class;
    }
}
