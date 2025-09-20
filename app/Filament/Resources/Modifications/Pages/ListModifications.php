<?php

namespace Modules\Core\Filament\Resources\Modifications\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Modifications\ModificationResource;
use Modules\Core\Filament\Utils\HasRecords;

class ListModifications extends ListRecords
{
    use HasRecords;

    protected static string $resource = ModificationResource::class;
}
