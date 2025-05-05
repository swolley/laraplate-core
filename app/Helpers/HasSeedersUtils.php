<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use ReflectionClass;
use Illuminate\Database\Eloquent\Model;

trait HasSeedersUtils
{
    /**
     * @param  class-string  $class
     */
    protected function create(string $class, array $attributes): Model
    {
        /** @var Model $model */
        $model = new $class();

        // Extract the relations from the attributes array
        $callables = array_filter($attributes, fn($value, $key): bool => (is_callable($value) || method_exists($class, $key)) && ! in_array($key, [...$model->getFillable(), ...$model->getHidden(), ...$model->getGuarded()], true), ARRAY_FILTER_USE_BOTH);

        // Remove the relations from the attributes array
        $model_attributes = array_diff_key($attributes, $callables);

        foreach ($model_attributes as $key => $value) {
            $model->{$key} = $value;
        }

        if (class_uses_trait($model, HasApprovals::class)) {
            /** @phpstan-ignore method.notFound */
            $model->setForcedApprovalUpdate(true);
        }

        $model->save();

        if ($callables !== []) {
            $reflected_class = new ReflectionClass($class);

            // Gestiamo le relazioni dopo il salvataggio
            foreach ($callables as $method => $value) {
                $value = is_callable($value) ? $value($model) : $value;
                $return_type = $reflected_class->getMethod($method)->getReturnType()?->getName();

                if ($return_type && is_subclass_of($return_type, \Illuminate\Database\Eloquent\Relations\Relation::class)) {
                    if ($return_type === \Illuminate\Database\Eloquent\Relations\BelongsToMany::class) {
                        $model->{$method}()->sync($value->pluck('id'));
                    } else {
                        $model->{$method}()->associate($value);
                    }
                } else {
                    $model->{$method}($value);
                }
            }
        }

        return $model;
    }

    /**
     * @param  class-string  $class
     * @param  array<int,array>  $items  Array di array di attributi
     * @return array<int,Model>
     */
    protected function createMany(string $class, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $timestamp = now();
        $models = [];
        $records = [];

        // Crea i model e prepara i dati per insert
        foreach ($items as $attributes) {
            $model = $class::make($attributes);

            if (class_uses_trait($model, HasApprovals::class)) {
                /** @phpstan-ignore method.notFound */
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
     * @param  class-string  $model
     */
    protected function logOperation(string $model): void
    {
        $already_exists = $model::query()->exists();
        $table = new $model()->getTable();
        $this->command->line('  ' . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>' . $table . '</>');
    }
}
