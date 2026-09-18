<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 * The query scopes HasLocks declares with #[Scope]. Named here because generic
 * code holds a Builder it cannot type to a concrete model, and an intersection
 * with this contract is what tells the analyser the scopes are there.
 *
 * @method static Builder<Model&static> locked()
 * @method Builder<Model&static> scopeLocked(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeUnlocked(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeLockedBy(Builder<Model&static> $query, User $user)
 * @method Builder<Model&static> scopeUnlockedBy(Builder<Model&static> $query, User $user)
 * @method static Builder<Model&static> unlocked()
 * @method static Builder<Model&static> lockedBy(User $user)
 * @method static Builder<Model&static> unlockedBy(User $user)
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
