<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\ACLS\ACLResource;

class EditACL extends EditRecord
{
    protected static string $resource = ACLResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
