<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasTranslations;
use Modules\Core\Search\Traits\Searchable;
use Override;

/**
 * Translated + embeddable model with NO `translation_fallback_enabled`
 * override, so `translationFallbackEnabledBySettings()` resolves to the
 * app-wide default (true) via `PerModelSettingResolver` — the same
 * configuration real translated models (e.g. Content) run under.
 *
 * Used to prove `Searchable::prepareDataToEmbedByLocale()` skips locales with
 * no translation row of their own instead of silently reusing the
 * default-locale translation for every available locale.
 */
class TranslatedSearchableStubModel extends Model
{
    use HasTranslations;
    use Searchable;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected array $embed = ['title'];

    #[Override]
    protected $table = 'translated_searchable_stub_models';

    #[Override]
    public function getTable(): string
    {
        return 'translated_searchable_stub_models';
    }
}
