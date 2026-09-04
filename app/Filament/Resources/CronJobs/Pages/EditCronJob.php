<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;
use Modules\Core\Filament\Utils\HasRecordLease;
use Override;

final class EditCronJob extends EditRecord
{
    use HasRecordLease;

    #[Override]
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
