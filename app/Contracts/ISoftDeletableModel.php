<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * A model whose rows are retired rather than removed, and whose soft deletion can
 * be switched off per installation.
 *
 * Supplied by {@see \Modules\Core\SoftDeletes\SoftDeletes}, which wraps Laravel's
 * own trait and adds the settings-driven switch. Every model extending
 * {@see \Modules\Core\Overrides\Model} has it, which is where the contract is
 * declared: generic code taking an `Illuminate\Database\Eloquent\Model` cannot know
 * that, and asked for `getDeletedAtColumn()` on faith.
 *
 * @method static Builder<static> onlyTrashed()
 * @method static Builder<static> withTrashed()
 * @method static Builder<static> withoutTrashed()
 */
interface ISoftDeletableModel
{
    /**
     * Laravel declares no return types on its own SoftDeletes methods, and an
     * interface that added them would be incompatible with every model inheriting
     * them. The @return tags carry the information instead.
     */
    /**
     * @phpstan-return string
     */
    public function getDeletedAtColumn();

    /**
     * @phpstan-return string
     */
    public function getQualifiedDeletedAtColumn();

    public function getIsDeletedColumn(): string;

    public function softDeletesEnabledBySettings(): bool;

    public function softDeletesEnabledInCode(): ?bool;

    /**
     * @phpstan-return bool|null
     */
    public function restore();

    /**
     * @phpstan-return bool
     */
    public function trashed();
}
