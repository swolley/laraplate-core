<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Locking\Traits\HasLocks;

final class Locked
{
    public function lockedAtColumn(): string
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return 'locked_at';
            }

            return config('core.locking.lock_at_column', 'locked_at');
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            return 'locked_user_id';
        }
    }

    public function canBeUnlocked($model): bool
    {
        $modelClass = $model::class;
        $canBeUnlocked = $this->classesThatCanBeUnlocked();
        $unlockAllowed = $this->unlockAllowed();

        return $unlockAllowed || in_array($modelClass, $canBeUnlocked, true);
    }

    public function cannotBeUnlocked($model): bool
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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

    public function preventsModificationsOnLockedObjects(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return false;
            }

            return config('core.locking.prevent_modifications_on_locked_objects', false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function allowsModificationsOnLockedObjects(): bool
    {
        return ! $this->preventsModificationsOnLockedObjects();
    }

    public function allowsNotificationsToLockedObjects(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return false;
            }

            return config('core.locking.prevent_notifications_to_locked_objects', false);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
