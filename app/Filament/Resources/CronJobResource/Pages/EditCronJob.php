<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\CronJobResource;

class EditCronJob extends EditRecord
{
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
