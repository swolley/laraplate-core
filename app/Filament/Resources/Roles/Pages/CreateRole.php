<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Roles\RoleResource;

final class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;
}
