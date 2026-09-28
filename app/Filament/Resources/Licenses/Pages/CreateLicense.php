<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateLicense extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = LicenseResource::class;
}
