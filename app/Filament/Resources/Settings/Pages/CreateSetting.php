<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Override;

final class CreateSetting extends CreateRecord
{
    #[Override]
    protected static string $resource = SettingResource::class;
}
