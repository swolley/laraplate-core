<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateCronJob extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = CronJobResource::class;
}
