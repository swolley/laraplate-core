<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Core\Filament\Resources\Fields\FieldResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\Core\Filament\Utils\ReportsApprovalOutcome;
use Override;

final class EditField extends EditRecord
{
    use HasCloseOrCancelFormAction;
    use ReportsApprovalOutcome;

    #[Override]
    protected static string $resource = FieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::approvalAwareDeleteAction(),
            self::approvalAwareForceDeleteAction(),
            self::approvalAwareRestoreAction(),
        ];
    }
}
