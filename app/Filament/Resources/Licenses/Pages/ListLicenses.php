<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Licenses\LicenseResource;
use Modules\Core\Filament\Utils\HasRecords;
use Override;

final class ListLicenses extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = LicenseResource::class;
}
