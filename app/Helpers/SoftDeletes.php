<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Modules\Core\Overrides\CustomSoftDeletingScope;

trait SoftDeletes
{
	use BaseSoftDeletes {
		bootSoftDeletes as protected baseBootSoftDeletes;
		initializeSoftDeletes as protected baseInitializeSoftDeletes;
	}

	/**
	 * Boot the soft deleting trait for a model.
	 *
	 * @return void
	 */
	protected static function bootSoftDeletes(): void
	{
		// Rimuoviamo lo scope predefinito che usa deleted_at
		static::withoutGlobalScope(new \Illuminate\Database\Eloquent\SoftDeletingScope);

		// Aggiungiamo il nostro scope personalizzato che usa is_deleted
		static::addGlobalScope(new CustomSoftDeletingScope);

		static::updating(function (Model $model): void {
			throw new UnauthorizedException('Cannot update a softdeleted model');
		});
	}

	/**
	 * Initialize the soft deleting trait for an instance.
	 *
	 * @return void
	 */
	public function initializeSoftDeletes()
	{
		$this->baseInitializeSoftDeletes();
		if (! isset($this->casts[$this->getIsDeletedColumn()])) {
			$this->casts[$this->getIsDeletedColumn()] = 'boolean';
		}
		if (! in_array($this->getDeletedAtColumn(), $this->hidden)) {
			$this->hidden[] = $this->getDeletedAtColumn();
		}
	}

	/**
	 * Get the name of the "is deleted" column.
	 *
	 * @return string
	 */
	public function getIsDeletedColumn(): string
	{
		return 'is_deleted';
	}

	/**
	 * Get the fully qualified "is deleted" column.
	 *
	 * @return string
	 */
	public function getQualifiedIsDeletedColumn(): string
	{
		return $this->qualifyColumn($this->getIsDeletedColumn());
	}
}
