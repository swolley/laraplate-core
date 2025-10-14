<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListACLs extends ListRecords
{
    use HasRecords;

    protected static string $resource = ACLResource::class;
}
