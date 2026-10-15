<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Filament\Resources\Resource;
use Modules\Core\Filament\Utils\HasTranslatedResourceLabels;
use Modules\Core\Models\Setting;

/**
 * A resource whose `entities` key does not exist, to prove the labels fall back to Filament's own.
 */
final class UntranslatedResourceHarness extends Resource
{
    use HasTranslatedResourceLabels;

    protected static ?string $model = Setting::class;

    public static function getEntityLabelKey(): string
    {
        return 'app.entities.zz_missing';
    }
}
