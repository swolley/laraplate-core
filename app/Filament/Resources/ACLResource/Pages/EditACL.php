<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\ACLResource;

class EditACL extends EditRecord
{
    protected static string $resource = ACLResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
