<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateUser extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = UserResource::class;
}
