<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Users\UserResource;
use Modules\Core\Filament\Utils\HasRecords;

final class ListUsers extends ListRecords
{
    use HasRecords;

    protected static string $resource = UserResource::class;
}
