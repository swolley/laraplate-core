<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasCloseOrCancelFormAction;
use Modules\Core\Filament\Utils\ReportsApprovalOutcome;
use Modules\Core\Models\Setting;
use Modules\Core\Services\SettingChangeConfirmations;
use Override;

/**
 * A change the writer cannot apply alone is captured as a pending modification instead of
 * being saved, so the page says so rather than reporting a save that did not happen.
 *
 * A module that registered a `ISettingChangeConfirmation` for the setting can ask for a
 * confirmation first: the save is held back and a modal shows the warning, and confirming runs
 * the normal save and then tells the module.
 */
final class EditSetting extends EditRecord
{
    use HasCloseOrCancelFormAction;
    use ReportsApprovalOutcome;

    public ?string $pendingWarningTitle = null;

    /**
     * @var list<string>
     */
    public array $pendingWarningLines = [];

    #[Override]
    protected static string $resource = SettingResource::class;

    private bool $changeConfirmed = false;

    public function confirmSettingChangeAction(): Action
    {
        return Action::make('confirmSettingChange')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $this->pendingWarningTitle ?? 'Confirm the change')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                collect($this->pendingWarningLines)
                    ->map(static fn (string $line): string => '<p>' . e($line) . '</p>')
                    ->implode(''),
            ))
            ->action(function (): void {
                $this->changeConfirmed = true;
                $this->save();
            });
    }

    protected function beforeSave(): void
    {
        if ($this->changeConfirmed) {
            return;
        }

        /** @var Setting $record */
        $record = $this->getRecord();
        $confirmation = app(SettingChangeConfirmations::class)->for($record->name);
        $data = $this->form->getState();

        if ($confirmation === null || ! array_key_exists('value', $data) || $data['value'] === $record->value) {
            return;
        }

        $warning = $confirmation->warn($record, $data['value']);

        if ($warning === null) {
            return;
        }

        $this->pendingWarningTitle = $warning->title;
        $this->pendingWarningLines = $warning->lines;
        $this->mountAction('confirmSettingChange');
        $this->halt();
    }

    protected function afterSave(): void
    {
        if (! $this->changeConfirmed) {
            return;
        }

        $this->changeConfirmed = false;

        /** @var Setting $record */
        $record = $this->getRecord();

        if ($this->sentForApproval) {
            return;
        }

        app(SettingChangeConfirmations::class)->for($record->name)?->confirmed($record, $record->value);
    }
}
