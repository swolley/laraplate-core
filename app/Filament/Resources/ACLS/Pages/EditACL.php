<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class EditACL extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = ACLResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
