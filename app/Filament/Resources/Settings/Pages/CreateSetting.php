<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Settings\SettingResource;

final class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;
}
