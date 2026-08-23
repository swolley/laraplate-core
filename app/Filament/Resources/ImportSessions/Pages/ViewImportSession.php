<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ImportSessions\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Core\Filament\Resources\ImportSessions\ImportSessionResource;
use Override;

final class ViewImportSession extends ViewRecord
{
    #[Override]
    protected static string $resource = ImportSessionResource::class;
}
