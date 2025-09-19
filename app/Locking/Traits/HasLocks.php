<?php

declare(strict_types=1);

namespace Modules\Core\Locking\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Locking\Locked;

trait HasLocks
{
    public static function bootHasLocks(): void
    {
        static::saving(function (Model $model): void {
            $lock_version = request('lock_version');

            if ($lock_version) {
                // @phpstan-ignore property.notFound
                $model->lock_version = $lock_version;
            }
        });

        static::saving(function (Model $model): void {
            // Remove is_locked from saving data
            unset($model->attributes[$model->getIsLockedColumn()]);
            unset($model->original[$model->getIsLockedColumn()]);
        });
    }

    /**
     * Initialize the locking trait for an instance.
     */
    public function initializeHasLocks(): void
    {
        if (! in_array($this->getIsLockedColumn(), $this->guarded, true)) {
            $this->guarded[] = $this->getIsLockedColumn();
        }

        if (! in_array($this->getIsLockedColumn(), $this->hidden, true)) {
            $this->hidden[] = $this->getIsLockedColumn();
        }

        if (! in_array($this->getLockedAtColumn(), $this->hidden, true)) {
            $this->hidden[] = $this->getLockedAtColumn();
        }

        if (! in_array($this->getLockedByColumn(), $this->hidden, true)) {
            $this->hidden[] = $this->getLockedByColumn();
        }
    }

    /**
     * Get the name of the "is locked" column.
     */
    public function getIsLockedColumn(): string
    {
        return 'is_locked';
    }

    public function getLockedAtColumn(): string
    {
        return new Locked()->lockedAtColumn();
    }

    public function getLockedByColumn(): string
    {
        return new Locked()->lockedByColumn();
    }

    public function lock(?User $user = null): self
    {
        $this->{$this->getLockedAtColumn()} = now();

        if ($user instanceof User) {
            $this->{$this->getLockedByColumn()} = $user->id;
        }
        $this->save();

        return $this;
    }

    public function lockBy(User $user): self
    {
        return $this->lock($user);
    }

    public function isLocked(): bool
    {
        return $this->{new Locked()->lockedAtColumn()} !== null;
    }

    public function isLockedBy(User $user): bool
    {
        return $this->isLocked() && $this->{new Locked()->lockedByColumn()} === $user->id;
    }

    public function isNotLocked(): bool
    {
        return ! $this->isLocked();
    }

    public function isNotLockedBy(User $user): bool
    {
        return $this->isNotLocked() && $this->{new Locked()->lockedByColumn()} !== $user->id;
    }

    public function unlock(): self
    {
        $locked = new Locked();
        $lock_by_column = $locked->lockedByColumn();

        if ($locked->cannotBeUnlocked($this)) {
            throw new CannotUnlockException('This model cannot be unlocked');
        }
        $locking_user = $this->{$lock_by_column};

        if ($locking_user && $locking_user !== Auth::id()) {
            throw new CannotUnlockException('This model cannot be unlocked because locked by another user');
        }

        $this->{$locked->lockedAtColumn()} = null;
        $this->{$lock_by_column} = null;
        $this->save();

        return $this;
    }

    public function isUnlocked(): bool
    {
        return ! $this->isLocked();
    }

    public function isUnlockedBy(User $user): bool
    {
        return ! $this->isLockedBy($user);
    }

    public function isNotUnlocked(): bool
    {
        return ! $this->isUnlocked();
    }

    public function isNotUnlockedBy(User $user): bool
    {
        return ! $this->isUnlockedBy($user);
    }

    public function toggleLock(?User $user = null): self
    {
        if ($this->isLocked()) {
            $this->unlock();
        } else {
            $this->lock($user);
        }

        return $this;
    }

    public function toggleLockBy(?User $user = null): self
    {
        if (! $user instanceof User) {
            $user = Auth::user();
        }

        if ($this->isLocked()) {
            $this->unlock();
        } else {
            $this->lock($user);
        }

        return $this;
    }

    public function wasUnlocked(): bool
    {
        return $this->getOriginal(new Locked()->lockedAtColumn()) === null;
    }

    public function wasUnlockedBy(User $user): bool
    {
        return $this->wasUnlocked() && $user->id === $this->getOriginal(new Locked()->lockedByColumn());
    }

    public function wasLocked(): bool
    {
        return $this->getOriginal(new Locked()->lockedAtColumn()) !== null;
    }

    public function wasLockedBy(User $user): bool
    {
        return $this->wasLocked() && $user->id === $this->getOriginal(new Locked()->lockedByColumn());
    }

    public function scopeLocked($query): void
    {
        $query->where(new Locked()->lockedAtColumn(), '!=', null);
    }

    public function scopeLockedBy($query, User $user): void
    {
        $this->scopeLocked($query);
        $query->where(new Locked()->lockedByColumn(), $user->id);
    }

    public function scopeUnlocked($query): void
    {
        $query->where(new Locked()->lockedAtColumn(), null);
    }

    public function scopeUnlockedBy($query, User $user): void
    {
        $this->scopeUnlocked($query);
        $query->where(new Locked()->lockedByColumn(), '!=', $user->id);
    }
}
