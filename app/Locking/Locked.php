<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;
use Throwable;

final class Locked
{
    public function lockedAtColumn(): string
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return 'locked_at';
            }

            return config('core.locking.lock_at_column', 'locked_at');
        } catch (Throwable) {
            return 'locked_at';
        }
    }

    public function lockedByColumn(): string
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return 'locked_user_id';
            }

            return config('core.locking.lock_by_column', 'locked_user_id');
        } catch (Throwable) {
            return 'locked_user_id';
        }
    }

    public function canBeUnlocked(object $model): bool
    {
        $modelClass = $model::class;
        $canBeUnlocked = $this->classesThatCanBeUnlocked();
        $unlockAllowed = $this->unlockAllowed();

        return $unlockAllowed || in_array($modelClass, $canBeUnlocked, true);
    }

    public function cannotBeUnlocked(object $model): bool
    {
        return ! $this->canBeUnlocked($model);
    }

    public function unlockAllowed(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return true;
            }

            return config('core.locking.unlock_allowed', true);
        } catch (Throwable) {
            return true;
        }
    }

    public function classesThatCanBeUnlocked(): array
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return [];
            }

            return config('core.locking.can_be_unlocked', []);
        } catch (Throwable) {
            return [];
        }
    }

    public function usesHasLocks(Model $model): bool
    {
        return in_array(HasLocks::class, class_uses($model), true);
    }

    public function doesNotUseHasLocks(Model $model): bool
    {
        return ! $this->usesHasLocks($model);
    }

    /**
     * Whether saves, deletes, and replicates on locked models should be blocked.
     *
     * Config: core.locking.prevent_modifications_on_locked_objects (runtime setting, DB overlay).
     * Used by {@see LockedModelSubscriber} on eloquent.saving, eloquent.deleting, eloquent.replicating.
     * When false (default), locked records can still be modified; when true, dirty changes throw {@see LockedModelException}.
     */
    public function preventsModificationsOnLockedObjects(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return false;
            }

            return config('core.locking.prevent_modifications_on_locked_objects', false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Inverse of {@see preventsModificationsOnLockedObjects()}; early-exit in {@see LockedModelSubscriber} when true.
     */
    public function allowsModificationsOnLockedObjects(): bool
    {
        return ! $this->preventsModificationsOnLockedObjects();
    }

    /**
     * Reads core.locking.prevent_notifications_to_locked_objects (name is misleading: returns the "prevent" flag, not "allow").
     *
     * Used by {@see LockedModelSubscriber::notificationSending()}: when this returns true, the listener returns false
     * and cancels the notification before checking whether the notifiable is locked.
     * When false, only locked {@see HasLocks} notifiables are blocked (via exception).
     */
    public function allowsNotificationsToLockedObjects(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return false;
            }

            return config('core.locking.prevent_notifications_to_locked_objects', false);
        } catch (Throwable) {
            return false;
        }
    }
}
