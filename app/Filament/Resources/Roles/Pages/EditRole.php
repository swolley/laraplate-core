<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Roles\RoleResource;
use Modules\Core\Filament\Utils\HasRecordLease;
use Override;

final class EditRole extends EditRecord
{
    use HasRecordLease;

    #[Override]
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
