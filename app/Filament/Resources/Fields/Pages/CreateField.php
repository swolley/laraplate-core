<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Core\Filament\Resources\Fields\FieldResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class CreateField extends CreateRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = FieldResource::class;
}
