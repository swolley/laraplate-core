<?php

declare(strict_types=1);

namespace Modules\Core\SoftDeletes;

use function property_exists;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Modules\Core\Overrides\CustomSoftDeletingScope;
use Modules\Core\Services\PerModelSettingResolver;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait SoftDeletes
{
    use BaseSoftDeletes {
        bootSoftDeletes as private baseBootSoftDeletes;
        initializeSoftDeletes as private baseInitializeSoftDeletes;
        performDeleteOnModel as private basePerformDeleteOnModel;
        restore as private baseRestore;
    }

    /**
     * Initialize the soft deleting trait for an instance.
     */
    public function initializeSoftDeletes(): void
    {
        $this->baseInitializeSoftDeletes();

        $guarded = $this->guarded;
        if (is_array($guarded) && ! in_array($this->getIsDeletedColumn(), $guarded, true)) {
            $this->guarded[] = $this->getIsDeletedColumn();
        }

        $hidden = $this->hidden;
        if (is_array($hidden) && ! in_array($this->getDeletedAtColumn(), $hidden, true)) {
            $this->hidden[] = $this->getDeletedAtColumn();
        }

        if (is_array($hidden) && ! in_array($this->getIsDeletedColumn(), $hidden, true)) {
            $this->hidden[] = $this->getIsDeletedColumn();
        }
    }

    /**
     * Get the name of the "is deleted" column.
     */
    public function getIsDeletedColumn(): string
    {
        return 'is_deleted';
    }

    /**
     * Get the fully qualified "is deleted" column.
     */
    public function getQualifiedIsDeletedColumn(): string
    {
        return $this->qualifyColumn($this->getIsDeletedColumn());
    }

    /**
     * Whether this model instance uses soft delete persistence (vs hard delete).
     * Reads optional per-model property {@see $softDeletesEnabled} or settings in group {@code soft_deletes}
     * with name {@code soft_deletes_{table}}. When no setting exists, soft deletes stay enabled.
     */
    public function softDeletesEnabledBySettings(): bool
    {
        if (property_exists($this, 'softDeletesEnabled')) {
            return (bool) $this->softDeletesEnabled;
        }

        return app(PerModelSettingResolver::class)->boolean(
            'soft_deletes_' . $this->getTable(),
            default: true,
        );
    }

    public function restore(): ?bool
    {
        if (! $this->softDeletesEnabledBySettings()) {
            return false;
        }

        return $this->baseRestore();
    }

    /**
     * Revive a soft-deleted instance in memory so the next save() persists the
     * restoration in a single write, without tripping the "Cannot update a
     * softdeleted model" guard. Both soft-delete columns are cleared because
     * {@see trashed()} inspects is_deleted first, and the saving hook alone does
     * not run early enough for every persistence path (e.g. optimistic locking).
     */
    public function reviveInMemory(): void
    {
        unset($this->attributes[$this->getIsDeletedColumn()], $this->original[$this->getIsDeletedColumn()]);

        $this->{$this->getDeletedAtColumn()} = null;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * Uses is_deleted / deleted_at from attributes or original so updates work when
     * nullable soft-delete columns were not hydrated after insert (Laravel 12 strict mode).
     */
    public function trashed(): bool
    {
        $is_deleted_column = $this->getIsDeletedColumn();

        if (array_key_exists($is_deleted_column, $this->attributes)) {
            return (bool) $this->attributes[$is_deleted_column];
        }

        $deleted_at_column = $this->getDeletedAtColumn();

        if (array_key_exists($deleted_at_column, $this->attributes)) {
            return $this->attributes[$deleted_at_column] !== null;
        }

        if (array_key_exists($is_deleted_column, $this->original)) {
            return (bool) $this->original[$is_deleted_column];
        }

        if (array_key_exists($deleted_at_column, $this->original)) {
            return $this->original[$deleted_at_column] !== null;
        }

        return false;
    }

    /**
     * Boot the soft deleting trait for a model.
     */
    protected static function bootSoftDeletes(): void
    {
        // Do not call baseBootSoftDeletes() (would register SoftDeletingScope) or
        // static::withoutGlobalScope() here: the latter is not a Model API and
        // resolves via __callStatic → query(), which requires a DB connection during
        // composer package:discover / ide-helper when the resolver is not set yet.
        static::addGlobalScope(new CustomSoftDeletingScope());

        static::updating(function (self $model): void {
            throw_if($model->trashed(), AuthorizationException::class, 'Cannot update a softdeleted model');
        });

        static::saving(function (self $model): void {
            $is_deleted_column = $model->getIsDeletedColumn();
            unset($model->attributes[$is_deleted_column], $model->original[$is_deleted_column]);
        });
    }

    protected function performDeleteOnModel(): void
    {
        if (! $this->softDeletesEnabledBySettings()) {
            $this->setKeysForSaveQuery($this->newModelQuery())->forceDelete();
            $this->exists = false;

            return;
        }

        $this->basePerformDeleteOnModel();
    }
}
