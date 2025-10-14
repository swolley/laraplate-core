<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Roles\RoleResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListRoles extends ListRecords
{
    use HasRecords;

    protected static string $resource = RoleResource::class;
}
