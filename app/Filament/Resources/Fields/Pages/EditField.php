<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Fields\FieldResource;
use Override;

final class EditField extends EditRecord
{
    #[Override]
    protected static string $resource = FieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
