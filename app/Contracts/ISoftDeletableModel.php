<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
 * @method static Builder<Model&static> onlyTrashed()
 * @method static Builder<Model&static> withTrashed()
 * @method static Builder<Model&static> withoutTrashed()
 *
 * The three below are not scopes: SoftDeletingScope::extend() macros them onto
 * the builder. The scope-prefixed tag is simply the form Larastan reads when
 * resolving a call made on a Builder, and what it states is true either way:
 * a model declaring this contract answers these on its query.
 *
 * @method Builder<Model&static> scopeOnlyTrashed(Builder<Model&static> $query)
 * @method Builder<Model&static> scopeWithTrashed(Builder<Model&static> $query, bool $withTrashed = true)
 * @method Builder<Model&static> scopeWithoutTrashed(Builder<Model&static> $query)
 * @method int scopeRestore(Builder<Model&static> $query)
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
