<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Services\Translation\Definitions\ITranslated;

/**
 * Translation row of {@see LocaleHiddenSearchStubModel}.
 */
class LocaleHiddenSearchStubTranslation extends Model implements ITranslated
{
    public $timestamps = false;

    protected $table = 'core_test_locale_hidden_search_stub_translations';

    protected $guarded = [];
}
