<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Media\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Core\Filament\Resources\Media\MediaResource;
use Override;

final class ViewMedia extends ViewRecord
{
    #[Override]
    protected static string $resource = MediaResource::class;
}
