<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\User;

/**
 * A model that can be held against concurrent edits.
 *
 * Implemented by every model using {@see \Modules\Core\Locking\Traits\HasLocks},
 * which supplies all of it. The contract exists so callers can say what they need
 * — a lockable record — instead of taking an Eloquent Model and hoping the trait
 * is there. Generic code (Filament tables, CrudService) receives models it cannot
 * name, and `instanceof ILockableModel` is both the runtime check and the type.
 *
 * @method static Builder<static> locked()
 * @method static Builder<static> notLocked()
 */
interface ILockableModel
{
    public function getIsLockedColumn(): string;

    public function getLockedAtColumn(): string;

    public function getLockedByColumn(): string;

    public function getLockedUntilColumn(): string;

    public function lockHasExpired(): bool;

    public function lock(?User $user = null, DateTimeInterface|string|null $until = null): self;

    public function lockBy(User $user, DateTimeInterface|string|null $until = null): self;

    public function isLocked(): bool;

    public function isLockedBy(User $user): bool;

    public function isNotLocked(): bool;

    public function setLockDeadline(DateTimeInterface|string|null $until): self;

    public function forceUnlock(): self;

    public function unlock(): self;

    public function isUnlocked(): bool;
}
