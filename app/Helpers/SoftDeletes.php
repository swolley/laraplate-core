<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use function property_exists;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\CustomSoftDeletingScope;

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

        // if (! isset($this->casts[$this->getIsDeletedColumn()])) {
        // 	$this->casts[$this->getIsDeletedColumn()] = 'boolean';
        // }
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

        $settings_name = "soft_deletes_{$this->getTable()}";

        $stored = Cache::rememberForever(
            'soft_deletes_flags',
            static fn () => Setting::query()->where('group_name', 'soft_deletes')->get(),
        )->firstWhere('name', $settings_name)?->value;

        if ($stored === null) {
            return true;
        }

        return (bool) $stored;
    }

    public function restore()
    {
        if (! $this->softDeletesEnabledBySettings()) {
            return false;
        }

        return $this->baseRestore();
    }

    /**
     * Boot the soft deleting trait for a model.
     */
    protected static function bootSoftDeletes(): void
    {
        // Rimuoviamo lo scope predefinito che usa deleted_at
        static::withoutGlobalScope(new SoftDeletingScope());

        // Aggiungiamo il nostro scope personalizzato che usa is_deleted
        static::addGlobalScope(new CustomSoftDeletingScope());

        static::updating(function (Model $model): void {
            throw_if($model->trashed(), UnauthorizedException::class, 'Cannot update a softdeleted model');
        });

        static::saving(function (Model $model): void {
            $is_deleted_column = $model->getIsDeletedColumn();

            // Rimuovi is_deleted dai dati da salvare
            unset($model->attributes[$is_deleted_column]);
            unset($model->original[$is_deleted_column]);
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
