<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\LicenseResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\LicenseResource;

class CreateLicense extends CreateRecord
{
    protected static string $resource = LicenseResource::class;
}
