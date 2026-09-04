<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Utils;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Locking\Locked;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\User;

/**
 * Takes an editorial lease when an edit page is opened, and says so when somebody else holds one.
 *
 * Opening the edit page is a statement of intent — Filament keeps viewing on its own page — so the
 * lease is taken here rather than on the first keystroke. There is no reliable "left the page" hook
 * in a SPA-ish panel, so nothing releases it explicitly: the deadline does. The client is the panel
 * itself, which reopens the page and takes a fresh lease if the old one lapsed.
 *
 * When the record is already held, the page does not decide for the user. It asks: cancel and go
 * back to the list, or open read-only. Opening read-only is genuinely read-only — the whole schema
 * is disabled — because letting somebody type into a form whose save the lock guard will refuse is
 * the one outcome worth ruling out.
 *
 * @phpstan-require-extends \Filament\Resources\Pages\EditRecord
 */
trait HasRecordLease
{
    /**
     * Set when the record is held by somebody else, or frozen. Livewire-public so it survives the
     * round trip that closes the modal.
     */
    public bool $record_is_read_only = false;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled(fn (): bool => $this->record_is_read_only);
    }

    /**
     * The choice offered when the record is not available for editing.
     *
     * Resolved by name from {@see \Filament\Actions\Concerns\InteractsWithActions::mountAction()},
     * which looks for a `<name>Action` method on the page.
     */
    public function recordHeldWarningAction(): Action
    {
        return Action::make('recordHeldWarning')
            ->modalHeading(__('core::app.locking.held.heading'))
            ->modalDescription(fn (): string => $this->recordHeldDescription())
            ->modalSubmitActionLabel(__('core::app.locking.held.open_read_only'))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label(__('core::app.locking.held.cancel'))
                ->url(static::getResource()::getUrl('index')))
            // The choice has to be made: no dismissing it by clicking away or hitting the corner.
            ->closeModalByClickingAway(false)
            ->modalCloseButton(false)
            ->action(static fn (): null => null);
    }

    /**
     * Called by {@see \Filament\Resources\Pages\EditRecord::fillFormWithDataAndCallHooks()} once the
     * record is resolved and the form filled.
     */
    protected function afterFill(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Model || ! class_uses_trait($record, HasLocks::class)) {
            return;
        }

        $user = Auth::user();

        if ($record->isLocked() && ! ($user instanceof User && $record->isLockedBy($user))) {
            $this->record_is_read_only = true;
            $this->mountAction('recordHeldWarning');

            return;
        }

        if (! $user instanceof User) {
            // A lease with no owner would be a freeze, blocking the record for everybody. Better no
            // lease at all than the wrong one.
            return;
        }

        $record->lockBy($user, now()->addSeconds(new Locked()->leaseTtl()));
    }

    private function recordHeldDescription(): string
    {
        $record = $this->getRecord();
        $locked = new Locked();

        $owner_id = $record->getAttribute($locked->lockedByColumn());
        $until = $record->getAttribute($locked->lockedUntilColumn());

        if ($owner_id === null) {
            return $until === null
                ? __('core::app.locking.held.frozen')
                : __('core::app.locking.held.frozen_until', ['until' => (string) $until]);
        }

        $owner = User::query()->find($owner_id)?->name ?? (string) $owner_id;

        return $until === null
            ? __('core::app.locking.held.by', ['user' => $owner])
            : __('core::app.locking.held.by_until', ['user' => $owner, 'until' => (string) $until]);
    }
}
