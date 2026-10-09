<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateUser extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = UserResource::class;

    /**
     * A service account has no usable password: the column cannot be empty, so it holds a random
     * secret that nobody is ever told.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['is_service_account'] ?? false) === true) {
            $data['password'] = Str::random(64);
        }

        return $data;
    }
}
