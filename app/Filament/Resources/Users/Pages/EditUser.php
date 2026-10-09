<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\Core\Filament\Utils\HasRecordLease;
use Override;

final class EditUser extends EditRecord
{
    use HasCloseOrCancelFormAction;
    use HasRecordLease;

    #[Override]
    protected static string $resource = UserResource::class;

    /**
     * An account turned into a service account loses its password: the secret is replaced by a random one
     * nobody is told, so the old password cannot be used to sign in if the flag is ever turned off again.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['is_service_account'] ?? false) === true && $this->getRecord()->getAttribute('is_service_account') !== true) {
            $data['password'] = Str::random(64);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
