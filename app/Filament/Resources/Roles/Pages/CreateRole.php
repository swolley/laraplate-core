<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Roles\RoleResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateRole extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = RoleResource::class;
}
