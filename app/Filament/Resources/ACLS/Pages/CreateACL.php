<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\ACLS\ACLResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateACL extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = ACLResource::class;
}
