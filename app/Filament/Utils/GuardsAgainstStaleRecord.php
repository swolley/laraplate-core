<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Notifications\Notification;
use Modules\Core\Contracts\IOptimisticLockableModel;
use Modules\Core\Locking\Exceptions\StaleModelLockingException;
use Override;

/**
 * Makes an edit page refuse to overwrite a record that changed after its form was opened.
 *
 * Livewire loads the record again from the database on every request, so by the time the form is
 * submitted the record already carries the newest lock version and the model-level check can never
 * notice a concurrent edit. The version the form was opened with travels in the form state instead,
 * and is compared with the record before anything is written.
 *
 * @phpstan-require-extends \Filament\Resources\Pages\EditRecord
 */
trait GuardsAgainstStaleRecord
{
    /**
     * A conflict is told to the user instead of being served as a server error. The page has already
     * rolled the transaction back when the exception reaches this point, so nothing was written.
     */
    #[Override]
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (StaleModelLockingException) {
            Notification::make()
                ->danger()
                ->persistent()
                ->title('This record was changed by someone else')
                ->body('Reload the page to see the latest version, then repeat your changes.')
                ->send();
        }
    }

    /**
     * Remembers the version the record had when the form was filled, after the page's own fill and
     * its hooks have run (the record lease uses the `afterFill` hook, and a trait cannot share it).
     */
    #[Override]
    protected function fillForm(): void
    {
        parent::fillForm();

        $record = $this->getRecord();

        if ($record instanceof IOptimisticLockableModel) {
            $this->data[$record::lockVersionColumn()] = $record->currentLockVersion();
        }
    }

    /**
     * Compares the remembered version with the one in the database, then puts it back on the record so
     * the update query is conditioned on it: a write that slips in between the comparison and the update
     * still fails instead of overwriting. A comparison is also needed because a save that touches no
     * column of the record itself, as an edit of translated text, never reaches the update query.
     */
    protected function beforeSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof IOptimisticLockableModel) {
            return;
        }

        $column = $record::lockVersionColumn();
        $expected = $this->data[$column] ?? null;

        if (! is_numeric($expected)) {
            return;
        }

        throw_if(
            $record->currentLockVersion() !== (int) $expected,
            StaleModelLockingException::class,
            'The record has been changed since the form was opened.',
        );

        $record->setAttribute($column, (int) $expected);
        $record->syncOriginalAttribute($column);
    }
}
