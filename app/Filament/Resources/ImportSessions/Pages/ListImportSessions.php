<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ImportSessions\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\ImportSessions\ImportSessionResource;
use Modules\Core\Filament\Utils\HasRecords;
use Override;

final class ListImportSessions extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = ImportSessionResource::class;

    /**
     * Import sessions are opened by the import API, never by hand, so the list
     * offers no "create" action.
     *
     * @return array<int, mixed>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
