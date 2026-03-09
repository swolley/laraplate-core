<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Services\Translation\Definitions\ITranslated;

class FakeTranslatableModelTranslation extends Model implements ITranslated
{
    protected $table = 'fake_translatable_model_translations';

    protected $fillable = [
        'fake_translatable_model_id',
        'locale',
        'title',
        'slug',
        'components',
    ];

    protected $casts = [
        'components' => 'array',
    ];
}

