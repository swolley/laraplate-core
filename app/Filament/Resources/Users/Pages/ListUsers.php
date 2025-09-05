<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Users\UserResource;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
