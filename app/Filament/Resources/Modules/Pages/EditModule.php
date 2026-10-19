<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modules\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Modules\ModuleResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

final class EditModule extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = ModuleResource::class;
}
