<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Permissions\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Permissions\PermissionResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreatePermission extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = PermissionResource::class;
}
