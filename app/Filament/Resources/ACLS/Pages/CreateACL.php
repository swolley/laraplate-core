<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Override;

final class CreateACL extends CreateRecord
{
    #[Override]
    protected static string $resource = ACLResource::class;
}
