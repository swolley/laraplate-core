<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\CronJobs\CronJobResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListCronJobs extends ListRecords
{
    use HasRecords;

    protected static string $resource = CronJobResource::class;
}
