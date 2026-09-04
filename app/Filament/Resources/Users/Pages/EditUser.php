<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Filament\Utils\HasRecordLease;
use Override;

final class EditUser extends EditRecord
{
    use HasRecordLease;

    #[Override]
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
