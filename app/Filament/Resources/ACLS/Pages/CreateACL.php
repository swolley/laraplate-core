<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\ACLS\ACLResource;

class CreateACL extends CreateRecord
{
    protected static string $resource = ACLResource::class;
}
