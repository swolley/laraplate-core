<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Media\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Resources\Media\MediaResource;
use Modules\Core\Filament\Utils\HasRecords;
use Override;

final class ListMedia extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = MediaResource::class;

    /**
     * Media are created by uploads on their owning record, never here, so the
     * gallery list offers no "create" action.
     *
     * @return array<int, mixed>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
