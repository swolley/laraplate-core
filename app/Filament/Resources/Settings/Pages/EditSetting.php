<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\Core\Filament\Utils\ReportsApprovalOutcome;
use Override;

/**
 * A change the writer cannot apply alone is captured as a pending modification instead of
 * being saved, so the page says so rather than reporting a save that did not happen.
 */
final class EditSetting extends EditRecord
{
    use HasCloseOrCancelFormAction;
    use ReportsApprovalOutcome;

    #[Override]
    protected static string $resource = SettingResource::class;
}
