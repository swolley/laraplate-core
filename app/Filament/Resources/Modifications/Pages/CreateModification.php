<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Modifications\ModificationResource;
use Override;

final class CreateModification extends CreateRecord
{
    #[Override]
    protected static string $resource = ModificationResource::class;
}
