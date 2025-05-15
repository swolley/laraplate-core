<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\CronJobResource;

class ListCronJobs extends ListRecords
{
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
