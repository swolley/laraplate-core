<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Modifications\ModificationResource;

class EditModification extends EditRecord
{
    protected static string $resource = ModificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
