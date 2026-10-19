<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modules\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Modules\ModuleResource;
use Modules\Core\Filament\Utils\HasRecords;
use Override;

final class ListModules extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ModuleResource::class;
}
