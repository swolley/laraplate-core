<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Fields\FieldResource;
use Override;

final class CreateField extends CreateRecord
{
    #[Override]
    protected static string $resource = FieldResource::class;
}
