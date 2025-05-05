<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Overrides\CustomSoftDeletingScope;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

trait SoftDeletes
{
    use BaseSoftDeletes {
        bootSoftDeletes as protected baseBootSoftDeletes;
        initializeSoftDeletes as protected baseInitializeSoftDeletes;
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
     * Boot the soft deleting trait for a model.
     */
    protected static function bootSoftDeletes(): void
    {
        // Rimuoviamo lo scope predefinito che usa deleted_at
        static::withoutGlobalScope(new \Illuminate\Database\Eloquent\SoftDeletingScope());

        // Aggiungiamo il nostro scope personalizzato che usa is_deleted
        static::addGlobalScope(new CustomSoftDeletingScope());

        static::updating(function (Model $model): void {
            if ($model->trashed()) {
                throw new UnauthorizedException('Cannot update a softdeleted model');
            }
        });

        static::saving(function (Model $model): void {
            // Rimuovi is_deleted dai dati da salvare
            unset($model->attributes[$model->getIsDeletedColumn()]);
            unset($model->original[$model->getIsDeletedColumn()]);
        });
    }
}
