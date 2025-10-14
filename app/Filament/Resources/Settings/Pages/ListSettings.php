<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListSettings extends ListRecords
{
    use HasRecords;

    protected static string $resource = SettingResource::class;
}
