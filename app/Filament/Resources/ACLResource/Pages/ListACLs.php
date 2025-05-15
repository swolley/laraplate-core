<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\ACLResource;

class ListACLs extends ListRecords
{
    protected static string $resource = ACLResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
