<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Override;

/**
 * A change the writer cannot apply alone is captured as a pending modification instead of
 * being saved, so the page says so rather than reporting a save that did not happen.
 */
final class EditSetting extends EditRecord
{
    use HasCloseOrCancelFormAction;

    #[Override]
    protected static string $resource = SettingResource::class;

    private bool $sentForApproval = false;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->fill($data);
        $this->sentForApproval = $record->isDirty() && ! $record->save();

        if ($this->sentForApproval) {
            $record->refresh();
        }

        return $record;
    }

    #[Override]
    protected function getSavedNotification(): ?Notification
    {
        if (! $this->sentForApproval) {
            return parent::getSavedNotification();
        }

        return Notification::make()
            ->warning()
            ->title('Change sent for approval')
            ->body('The setting keeps its current value until the change is approved in Modifications.');
    }
}
