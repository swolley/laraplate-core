<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\DynamicContentsService;
use Modules\Core\Services\PresetVersioningService;
use Modules\Core\Tests\Stubs\Casts\EntityTypeStub;

beforeEach(function (): void {
    if (! Schema::hasColumns('contents', ['components', 'shared_components'])) {
        $this->markTestSkipped('Preset versioning integration requires full Core runtime.');
    }
});

/**
 * Create an entity + preset with fields and a versioned presettable.
 *
 * @return array{entity: Entity, preset: Preset, fields: array<Field>, presettable: Presettable}
 */
function createPresetWithFields(int $field_count = 2): array
{
    $entity = Entity::query()->create([
        'name' => 'test_entity_' . uniqid(),
        'type' => EntityTypeStub::VALUE1,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'test_preset_' . uniqid(),
    ]);

    $fields = [];

    for ($i = 0; $i < $field_count; $i++) {
        $field = Field::query()->create([
            'name' => 'field_' . uniqid(),
            'type' => FieldType::TEXT,
            'options' => new stdClass(),
        ]);
        $field->is_translatable = $i === 0;
        $field->save();
        $fields[] = $field;
    }

    $preset->fields()->attach(
        collect($fields)->mapWithKeys(fn (Field $field, int $index): array => [
            $field->id => [
                'is_required' => $index === 0,
                'order_column' => $index,
                'default' => null,
            ],
        ])->all(),
    );

    $presettable = $preset->createFieldsVersion();

    return ['entity' => $entity, 'preset' => $preset, 'fields' => $fields, 'presettable' => $presettable];
}

describe('PresetVersioningService', function (): void {
    it('creates a versioned presettable with correct fields snapshot', function (): void {
        ['presettable' => $presettable, 'fields' => $fields] = createPresetWithFields();

        expect($presettable->fields_snapshot)->toBeArray();
        expect($presettable->fields_snapshot)->toHaveCount(2);

        $snapshot_names = array_column($presettable->fields_snapshot, 'name');
        expect($snapshot_names)->toContain($fields[0]->name, $fields[1]->name);
    });

    it('captures field properties in the snapshot', function (): void {
        ['presettable' => $presettable, 'fields' => $fields] = createPresetWithFields(1);

        $snapshot_field = $presettable->fields_snapshot[0];

        expect($snapshot_field['field_id'])->toBe($fields[0]->id);
        expect($snapshot_field['name'])->toBe($fields[0]->name);
        expect($snapshot_field['type'])->toBe(FieldType::TEXT->value);
        expect($snapshot_field['is_translatable'])->toBeTrue();
        expect($snapshot_field['pivot']['is_required'])->toBeTrue();
        expect($snapshot_field['pivot']['order_column'])->toBeInt();
    });

    it('auto-increments version numbers per preset', function (): void {
        ['preset' => $preset] = createPresetWithFields();

        $v1 = Presettable::query()
            ->withTrashed()
            ->where('preset_id', $preset->id)
            ->orderBy('version')
            ->first();

        $service = resolve(PresetVersioningService::class);
        $v2 = $service->createVersion($preset);

        expect($v1->version)->toBeLessThan($v2->version);
        expect($v2->version)->toBeGreaterThan(1);
    });

    it('soft-deletes previous active version when creating new one', function (): void {
        ['preset' => $preset, 'presettable' => $v1] = createPresetWithFields();

        $service = resolve(PresetVersioningService::class);
        $v2 = $service->createVersion($preset);

        $v1->refresh();

        expect($v1->deleted_at)->not->toBeNull();
        expect($v2->deleted_at)->toBeNull();
    });

    it('preserves old version snapshot when fields change', function (): void {
        ['preset' => $preset, 'presettable' => $v1, 'fields' => $fields] = createPresetWithFields();

        $original_snapshot = $v1->fields_snapshot;

        $new_field = Field::query()->create([
            'name' => 'new_field_' . uniqid(),
            'type' => FieldType::NUMBER,
            'options' => new stdClass(),
        ]);
        $preset->fields()->attach($new_field->id, [
            'is_required' => false,
            'order_column' => 10,
            'default' => null,
        ]);

        $v2 = $preset->createFieldsVersion();

        $v1->refresh();

        expect($v1->fields_snapshot)->toBe($original_snapshot);
        expect($v1->fields_snapshot)->toHaveCount(2);
        expect($v2->fields_snapshot)->toHaveCount(3);
    });

    it('hydrates Field models from snapshot correctly', function (): void {
        ['presettable' => $presettable, 'fields' => $fields] = createPresetWithFields();

        $hydrated = $presettable->getFieldsFromSnapshot();

        expect($hydrated)->toHaveCount(2);
        expect($hydrated->first()->name)->toBe($fields[0]->name);
        expect($hydrated->first()->type)->toBe(FieldType::TEXT);
        expect($hydrated->first()->getRelation('pivot')->is_required)->toBeTrue();
    });

    it('snapshot fields are sorted by order_column', function (): void {
        ['presettable' => $presettable, 'fields' => $fields] = createPresetWithFields();

        $hydrated = $presettable->getFieldsFromSnapshot();

        $first_order = $hydrated->first()->getRelation('pivot')->order_column;
        $last_order = $hydrated->last()->getRelation('pivot')->order_column;
        expect($first_order)->toBeLessThan($last_order);
    });
});

describe('Content uses presettable snapshot', function (): void {
    it('reads dynamic fields from presettable snapshot', function (): void {
        ['entity' => $entity, 'presettable' => $presettable, 'fields' => $fields] = createPresetWithFields(1);

        $content = Content::withoutSyncingToSearch(fn () => Content::factory()->create([
            'entity_id' => $entity->id,
            'presettable_id' => $presettable->id,
        ]));

        $dynamic_fields = $content->getDynamicFields();
        expect($dynamic_fields)->toContain($fields[0]->name);
    });

    it('content preserves old field structure after preset changes', function (): void {
        ['entity' => $entity, 'preset' => $preset, 'presettable' => $v1, 'fields' => $fields] = createPresetWithFields(1);

        $content = Content::withoutSyncingToSearch(fn () => Content::factory()->create([
            'entity_id' => $entity->id,
            'presettable_id' => $v1->id,
        ]));

        $new_field = Field::query()->create([
            'name' => 'extra_field_' . uniqid(),
            'type' => FieldType::TEXT,
            'options' => new stdClass(),
        ]);
        $preset->fields()->attach($new_field->id, [
            'is_required' => false,
            'order_column' => 5,
            'default' => null,
        ]);
        $preset->createFieldsVersion();

        $content->refresh();

        $dynamic_fields = $content->getDynamicFields();
        expect($dynamic_fields)->toContain($fields[0]->name);
        expect($dynamic_fields)->not->toContain($new_field->name);
    });

    it('new content uses latest active presettable', function (): void {
        ['entity' => $entity, 'preset' => $preset, 'fields' => $fields] = createPresetWithFields(1);

        DynamicContentsService::reset();

        $new_field = Field::query()->create([
            'name' => 'added_field_' . uniqid(),
            'type' => FieldType::TEXT,
            'options' => new stdClass(),
        ]);
        $preset->fields()->attach($new_field->id, [
            'is_required' => false,
            'order_column' => 5,
            'default' => null,
        ]);
        $v2 = $preset->createFieldsVersion();

        DynamicContentsService::reset();

        $content = Content::withoutSyncingToSearch(fn () => Content::factory()->create([
            'entity_id' => $entity->id,
            'presettable_id' => $v2->id,
        ]));

        $dynamic_fields = $content->getDynamicFields();
        expect($dynamic_fields)->toContain($fields[0]->name);
        expect($dynamic_fields)->toContain($new_field->name);
    });
});

describe('DynamicContentsService with versioning', function (): void {
    it('returns only active presettables', function (): void {
        ['entity' => $entity, 'preset' => $preset] = createPresetWithFields();

        DynamicContentsService::reset();

        $presettables = DynamicContentsService::getInstance()
            ->fetchAvailablePresettables(EntityTypeStub::VALUE1);

        $preset_presettables = $presettables->where('preset_id', $preset->id);

        expect($preset_presettables)->toHaveCount(1);
        expect($preset_presettables->first()->deleted_at)->toBeNull();
    });

    it('excludes soft-deleted presettable versions', function (): void {
        ['entity' => $entity, 'preset' => $preset] = createPresetWithFields();

        $service = resolve(PresetVersioningService::class);
        $service->createVersion($preset);

        DynamicContentsService::reset();

        $presettables = DynamicContentsService::getInstance()
            ->fetchAvailablePresettables(EntityTypeStub::VALUE1);

        $preset_presettables = $presettables->where('preset_id', $preset->id);

        expect($preset_presettables)->toHaveCount(1);
    });
});

describe('Presettable model', function (): void {
    it('is created automatically when a preset is created', function (): void {
        $entity = Entity::query()->create([
            'name' => 'auto_entity_' . uniqid(),
            'type' => EntityTypeStub::VALUE1,
        ]);

        $preset = Preset::query()->create([
            'entity_id' => $entity->id,
            'name' => 'auto_preset_' . uniqid(),
        ]);

        $presettable = Presettable::query()
            ->where('preset_id', $preset->id)
            ->where('entity_id', $entity->id)
            ->first();

        expect($presettable)->not->toBeNull();
        expect($presettable->version)->toBe(1);
        expect($presettable->fields_snapshot)->toBe([]);
    });

    it('allows loading soft-deleted presettable through withTrashed relation', function (): void {
        ['preset' => $preset, 'presettable' => $v1] = createPresetWithFields();

        $service = resolve(PresetVersioningService::class);
        $service->createVersion($preset);

        $v1->refresh();
        expect($v1->trashed())->toBeTrue();

        $loaded = Presettable::withTrashed()->find($v1->id);
        expect($loaded)->not->toBeNull();
        expect($loaded->fields_snapshot)->toBeArray();
    });

    it('returns empty collection for empty snapshot', function (): void {
        $entity = Entity::query()->create([
            'name' => 'empty_entity_' . uniqid(),
            'type' => EntityTypeStub::VALUE1,
        ]);

        $preset = Preset::query()->create([
            'entity_id' => $entity->id,
            'name' => 'empty_preset_' . uniqid(),
        ]);

        $presettable = Presettable::query()
            ->where('preset_id', $preset->id)
            ->first();

        expect($presettable->getFieldsFromSnapshot())->toHaveCount(0);
    });
});
