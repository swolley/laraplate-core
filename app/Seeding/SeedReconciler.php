<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;

final class SeedReconciler
{
    /**
     * Align persisted rows with their declared definition.
     *
     * Query budget is fixed: one read, one upsert, one restore, one baseline
     * backfill. Each write runs only when its set is non-empty.
     */
    public function reconcile(SeedDefinition $definition): ReconciliationOutcome
    {
        $column = $definition->identityColumn();
        $model_class = $definition->modelClass;

        /** @var Model $model */
        $model = new $model_class();

        $existing = $model_class::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->whereIn($column, array_column($definition->rows, $column))
            ->get()
            ->keyBy($column);

        $upsert_payload = [];
        $created = [];
        $realigned = [];
        $restored = [];
        $needs_baseline = [];
        $unchanged = 0;

        foreach ($definition->rows as $row) {
            $key = (string) $row[$column];
            $current = $existing->get($key);

            if ($current === null) {
                $created[] = $key;
                $upsert_payload[] = $this->fullPayload($definition, $row, $model);

                continue;
            }

            if ($current->trashed()) {
                $restored[] = $key;
            }

            if ($definition->initial !== [] && $current->getAttribute('seeded_value') === null) {
                // Reuse the full row, not a bare {column, module, seeded_value}
                // tuple: upsert() still performs a real INSERT under the hood,
                // and a partial row would trip NOT NULL constraints on columns
                // outside the conflict target. Only seeded_value/module are
                // actually written — see the $update list below.
                $needs_baseline[] = $this->fullPayload($definition, $row, $model);
            }

            if ($this->structuralDiffers($definition, $current, $row)) {
                $realigned[] = $key;
                $upsert_payload[] = $this->fullPayload($definition, $row, $model);

                continue;
            }

            $unchanged++;
        }

        $connection = $model->getConnection();

        $connection->transaction(function () use (
            $model_class,
            $definition,
            $column,
            $upsert_payload,
            $restored,
            $needs_baseline,
        ): void {
            if ($upsert_payload !== []) {
                // Structural columns only: value, seeded_value and module are
                // written on insert and must never be moved by a realignment.
                $model_class::query()->upsert($upsert_payload, [$column], $definition->structural);
            }

            if ($restored !== []) {
                $model_class::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->whereIn($column, $restored)
                    ->restore();
            }

            if ($needs_baseline !== []) {
                $model_class::query()
                    ->upsert($needs_baseline, [$column], ['seeded_value', 'module']);
            }
        });

        return new ReconciliationOutcome($created, $realigned, $restored, $unchanged);
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    private function fullPayload(SeedDefinition $definition, array $row, Model $model): array
    {
        $payload = $row;
        $payload['module'] = $definition->module;

        foreach ($definition->initial as $field) {
            if (array_key_exists($field, $row)) {
                $payload['seeded_value'] = $row[$field];
            }
        }

        return $this->encodeJsonCasts($model, $payload);
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function structuralDiffers(SeedDefinition $definition, Model $current, array $row): bool
    {
        foreach ($definition->structural as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }

            if (! ValueComparator::equal($current->getAttribute($field), $row[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * upsert() bypasses Eloquent casts entirely, so any column the model casts
     * to an array-like type (json/array/object/collection) must be hand-encoded
     * here or the raw PHP value would reach the database uncoerced. This walks
     * every payload field rather than trusting the definition's own field
     * lists, so a structural json column (e.g. Setting::$choices) is encoded
     * exactly like an initial one.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function encodeJsonCasts(Model $model, array $payload): array
    {
        $casts = $model->getCasts();

        foreach ($payload as $field => $value) {
            if ($value === null || ! isset($casts[$field])) {
                continue;
            }

            if (in_array($casts[$field], ['array', 'json', 'object', 'collection'], true)) {
                $payload[$field] = json_encode($value, JSON_THROW_ON_ERROR);
            }
        }

        return $payload;
    }
}
