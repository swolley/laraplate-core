<?php

namespace Modules\Core\Helpers;

use Modules\Core\Helpers\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasSeedersUtils
{
	/** 
	 * @param class-string $class 
	 */
	protected function create(string $class, array $attributes): Model
	{
		$model = $class::make($attributes);
		if (class_uses_trait($model, HasApprovals::class)) $model->setForcedApprovalUpdate(true);
		$model->save();
		return $model;
	}

	/** 
	 * @param class-string $class 
	 * @param array<int,array> $items Array di array di attributi
	 * @return array<int,Model>
	 */
	protected function createMany(string $class, array $items): array
	{
		if (empty($items)) {
			return [];
		}

		$timestamp = now();
		$models = [];
		$records = [];

		// Crea i model e prepara i dati per insert
		foreach ($items as $attributes) {
			$model = $class::make($attributes);

			if (class_uses_trait($model, HasApprovals::class)) {
				$model->setForcedApprovalUpdate(true);
			}

			// Aggiungi timestamps se il model li usa
			if ($model->usesTimestamps()) {
				$model->setCreatedAt($timestamp);
				$model->setUpdatedAt($timestamp);
			}

			$records[] = $model->attributesToArray();
			$models[] = $model;
		}

		// Esegui una singola query di insert
		$class::query()->insert($records);

		// Se il model ha un incrementing ID, aggiorna gli ID dei model
		if ($models[0]->getIncrementing()) {
			$firstId = $this->db->getPdo()->lastInsertId();
			foreach ($models as $index => $model) {
				$model->setAttribute($model->getKeyName(), $firstId + $index);
				$model->syncOriginal();
			}
		}

		return $models;
	}

	/**
	 * @param class-string $model
	 * @return void 
	 */
	protected function logOperation(string $model): void
	{
		$already_exists = $model::query()->exists();
		$table = (new $model)->getTable();
		$this->command->line("  " . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>' . $table . '</>');
	}
}
