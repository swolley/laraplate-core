<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\CronJobResource;

class CreateCronJob extends CreateRecord
{
    protected static string $resource = CronJobResource::class;
}
