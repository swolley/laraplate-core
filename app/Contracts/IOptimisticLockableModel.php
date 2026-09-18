<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model that guards concurrent writes with a version column.
 *
 * Supplied by {@see \Modules\Core\Locking\Traits\HasOptimisticLocking}. Separate
 * from {@see ILockableModel}, which is the deliberate, human-held lock: a record
 * can carry either, both, or neither. Generic code (the shared form builder above
 * all) needs to say "a model that versions its writes" without naming a trait,
 * which is not a type.
 */
interface IOptimisticLockableModel
{
    /**
     * The column holding the version this model checks on update.
     */
    public static function lockVersionColumn(): string;

    /**
     * The version currently loaded on this instance, null before the first save.
     */
    public function currentLockVersion(): ?int;
}
