<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

trait HasSeedersUtils
{
    /**
     * Create a model from attributes.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @param  array<string,mixed>  $attributes
     * @param  array<string,mixed>  $pivotValues
     * @return TModel
     */
    protected function create(string $class, array $attributes, array $pivotValues = []): Model
    {
        /** @var Model $model */
        $model = new $class();

        // Extract the relations from the attributes array
        $callables = array_filter($attributes, fn ($value, $key): bool => (is_callable($value) || method_exists($class, $key)) && ! in_array($key, [...$model->getFillable(), ...$model->getHidden(), ...$model->getGuarded()], true), ARRAY_FILTER_USE_BOTH);

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

                if ($return_type && is_subclass_of($return_type, Relation::class)) {
                    $relation = $model->{$method}();

                    if ($return_type === BelongsToMany::class) {
                        /**
                         * @var BelongsToMany<Model> $relation
                         * @var Collection<int,Model> $value
                         */
                        if (isset($pivotValues[$method])) {
                            $relation->syncWithPivotValues($value->pluck('id'), $pivotValues[$method], false);
                        } else {
                            $relation->syncWithoutDetaching($value->pluck('id'));
                        }
                    } else {
                        /**
                         * @var BelongsTo<Model> $relation
                         * @var Model|int $value
                         */
                        $relation->associate($value);
                    }
                } else {
                    $model->{$method}($value);
                }
            }
        }

        return $model;
    }

    /**
     * Create many models from factories.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @param  array<int,array<string,mixed>>  $items  Array di array di attributi
     * @return Collection<int,TModel>
     */
    protected function createMany(string $class, array $items): Collection
    {
        if ($items === []) {
            return collect();
        }

        $timestamp = now();
        $models = [];
        $records = [];

        // Crea i model e prepara i dati per insert
        foreach ($items as $attributes) {
            $model = $class::query()->make($attributes);

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
            $firstId = DB::getPdo()->lastInsertId();

            foreach ($models as $index => $model) {
                $model->setAttribute($model->getKeyName(), $firstId + $index);
                $model->syncOriginal();
            }
        }

        // Force garbage collection after bulk insert to free memory
        unset($records);
        gc_collect_cycles();

        return collect($models);
    }

    /**
     * @param  class-string  $model
     */
    protected function logOperation(string $model): void
    {
        $already_exists = $model::query()->exists();
        $table = new ReflectionClass($model)->newInstanceWithoutConstructor()->getTable();
        $this->command->line('  ' . ($already_exists ? 'Updating' : 'Creating') . ' default <fg=cyan;options=bold>' . $table . '</>');
    }
}
