<?php

declare(strict_types=1);

namespace Modules\Core\SoftDeletes;

use function property_exists;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Overrides\CustomSoftDeletingScope;

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

        if (! in_array($this->getIsDeletedColumn(), $this->guarded, true)) {
            $this->guarded[] = $this->getIsDeletedColumn();
        }

        if (! in_array($this->getDeletedAtColumn(), $this->hidden, true)) {
            $this->hidden[] = $this->getDeletedAtColumn();
        }

        if (! in_array($this->getIsDeletedColumn(), $this->hidden, true)) {
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

    public function restore()
    {
        if (! $this->softDeletesEnabledBySettings()) {
            return false;
        }

        return $this->baseRestore();
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

        static::updating(function (Model $model): void {
            throw_if($model->trashed(), AuthorizationException::class, 'Cannot update a softdeleted model');
        });

        static::saving(function (Model $model): void {
            $is_deleted_column = $model->getIsDeletedColumn();
            unset($model->attributes[$is_deleted_column], $model->original[$is_deleted_column]);
        });
    }

    protected function performDeleteOnModel(): mixed
    {
        if (! $this->softDeletesEnabledBySettings()) {
            return tap($this->setKeysForSaveQuery($this->newModelQuery())->forceDelete(), function (): void {
                $this->exists = false;
            });
        }

        return $this->basePerformDeleteOnModel();
    }
}
