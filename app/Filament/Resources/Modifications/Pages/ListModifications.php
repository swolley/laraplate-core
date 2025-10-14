<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Modifications\ModificationResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListModifications extends ListRecords
{
    use HasRecords;

    protected static string $resource = ModificationResource::class;
}
