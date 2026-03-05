<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Override;

final class EditSetting extends EditRecord
{
    #[Override]
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
