<?php

declare(strict_types=1);

use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use Modules\Core\Observers\FieldableObserver;

beforeEach(function (): void {
    setupCmsEntities();
});

it('creates preset version when fieldable pivot is saved', function (): void {
    $preset = Preset::query()->firstOrFail();
    $rows_before = Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count();

    $field = Field::query()->create([
        'name' => 'obs_field_' . uniqid(),
        'type' => FieldType::TEXT,
        'options' => new stdClass(),
    ]);

    Fieldable::query()->create([
        'field_id' => $field->id,
        'preset_id' => $preset->id,
        'order_column' => 1,
        'default' => null,
        'is_required' => false,
    ]);

    expect(Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count())->toBeGreaterThan($rows_before);
});

it('creates preset version when fieldable pivot is deleted', function (): void {
    $preset = Preset::query()->firstOrFail();
    $field = Field::query()->create([
        'name' => 'obs_field_del_' . uniqid(),
        'type' => FieldType::TEXT,
        'options' => new stdClass(),
    ]);

    $pivot = Fieldable::query()->create([
        'field_id' => $field->id,
        'preset_id' => $preset->id,
        'order_column' => 1,
        'default' => null,
        'is_required' => false,
    ]);

    $rows_before_delete = Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count();

    $pivot->delete();

    expect(Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count())->toBeGreaterThan($rows_before_delete);
});

it('skips versioning when preset id does not resolve', function (): void {
    $observer = app(FieldableObserver::class);
    $fieldable = new Fieldable(['preset_id' => 999_999_999]);

    $method = new ReflectionMethod(FieldableObserver::class, 'createVersionForPreset');
    $method->setAccessible(true);
    $method->invoke($observer, $fieldable);

    expect(Preset::query()->find(999_999_999))->toBeNull();
});
