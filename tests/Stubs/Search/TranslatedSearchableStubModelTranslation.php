<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Services\Translation\Definitions\ITranslated;
use Override;

class TranslatedSearchableStubModelTranslation extends Model implements ITranslated
{
    public $timestamps = false;

    #[Override]
    protected $table = 'translated_searchable_stub_model_translations';

    #[Override]
    protected $fillable = [
        'translated_searchable_stub_model_id',
        'locale',
        'title',
    ];
}
