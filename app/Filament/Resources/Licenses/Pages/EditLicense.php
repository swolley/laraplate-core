<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class EditLicense extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
