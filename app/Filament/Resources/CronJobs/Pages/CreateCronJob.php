<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;

class CreateCronJob extends CreateRecord
{
    protected static string $resource = CronJobResource::class;
}
