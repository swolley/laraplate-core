<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;

final class CreateLicense extends CreateRecord
{
    protected static string $resource = LicenseResource::class;
}
