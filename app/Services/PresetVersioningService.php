<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;

final class PresetVersioningService
{
    /**
     * Create a new presettable version for the given preset.
     * Soft-deletes the current active version and inserts a new one
     * with a fresh fields snapshot.
     */
    public function createVersion(Preset $preset): Presettable
    {
        return DB::transaction(function () use ($preset): Presettable {
            Presettable::query()
                ->where('preset_id', $preset->id)
                ->where('entity_id', $preset->entity_id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);

            /** @var Presettable $presettable */
            $presettable = Presettable::query()->forceCreate([
                'preset_id' => $preset->id,
                'entity_id' => $preset->entity_id,
                'fields_snapshot' => $this->captureFieldsSnapshot($preset),
            ]);

            return $presettable->load(['preset', 'entity']);
        });
    }

    /**
     * Capture the current field configuration for the given preset.
     *
     * @return array<int, array{field_id: int, name: string, type: string, options: mixed, is_translatable: bool, is_slug: bool, pivot: array{is_required: bool, order_column: int, default: mixed}}>
     */
    public function captureFieldsSnapshot(Preset $preset): array
    {
        return $preset->fields()
            ->orderBy('fieldables.order_column', 'asc')
            ->get()
            ->map(static fn (Field $field): array => [
                'field_id' => $field->id,
                'name' => $field->name,
                'type' => $field->type->value,
                'options' => json_decode(json_encode($field->options), true),
                'is_translatable' => (bool) $field->is_translatable,
                'is_slug' => (bool) $field->is_slug,
                'pivot' => [
                    'is_required' => (bool) $field->pivot->is_required,
                    'order_column' => (int) $field->pivot->order_column,
                    'default' => $field->pivot->default,
                ],
            ])
            ->all();
    }
}
