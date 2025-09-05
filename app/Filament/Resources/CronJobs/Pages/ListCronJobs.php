<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;

class ListCronJobs extends ListRecords
{
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
