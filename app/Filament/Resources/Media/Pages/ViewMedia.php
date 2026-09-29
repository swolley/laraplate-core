<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Media\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Core\Filament\Resources\Media\MediaResource;
use Modules\Core\Filament\ResourceSchemaContributorRegistry;
use Override;

final class ViewMedia extends ViewRecord
{
    #[Override]
    protected static string $resource = MediaResource::class;

    /**
     * Header actions contributed for this media through the Core resource-schema
     * seam (M22): a module (AI) registers its record actions without Core knowing it.
     *
     * @return array<int, mixed>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return app(ResourceSchemaContributorRegistry::class)->recordActionsFor($this->getRecord());
    }
}
