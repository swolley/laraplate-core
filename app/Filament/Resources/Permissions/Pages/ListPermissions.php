<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Permissions\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Permissions\PermissionResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListPermissions extends ListRecords
{
    use HasRecords;

    protected static string $resource = PermissionResource::class;
}
