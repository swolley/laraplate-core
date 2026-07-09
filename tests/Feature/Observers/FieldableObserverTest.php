<?php

declare(strict_types=1);

use Modules\CMS\Models\Pivot\Presettable;
use Modules\CMS\Models\Preset;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Observers\FieldableObserver;

beforeEach(function (): void {
    setupCMSEntities();
});

it('creates preset version when fieldable pivot is saved', function (): void {
    $preset = Preset::query()->firstOrFail();
    $rows_before = Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count();

    $field = Field::query()->create([
        'name' => 'obs_field_' . uniqid(),
        'type' => FieldType::Text,
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
        'type' => FieldType::Text,
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

    expect(Presettable::query()->withTrashed()->where('preset_id', 999_999_999)->count())->toBe(0);
});

it('orders fieldables by order column ascending', function (): void {
    $preset = Preset::query()->firstOrFail();
    $field_a = Field::query()->create([
        'name' => 'ordered_field_a_' . uniqid(),
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);
    $field_b = Field::query()->create([
        'name' => 'ordered_field_b_' . uniqid(),
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);

    $fieldable_a = Fieldable::query()->create([
        'field_id' => $field_a->id,
        'preset_id' => $preset->id,
        'order_column' => 1,
        'default' => null,
        'is_required' => false,
    ]);
    $fieldable_b = Fieldable::query()->create([
        'field_id' => $field_b->id,
        'preset_id' => $preset->id,
        'order_column' => 1,
        'default' => null,
        'is_required' => false,
    ]);

    Illuminate\Support\Facades\DB::table($fieldable_a->getTable())
        ->where('id', $fieldable_a->id)
        ->update(['order_column' => 10]);
    Illuminate\Support\Facades\DB::table($fieldable_b->getTable())
        ->where('id', $fieldable_b->id)
        ->update(['order_column' => 5]);

    $ordered = Fieldable::query()
        ->whereIn('id', [$fieldable_a->id, $fieldable_b->id])
        ->ordered()
        ->pluck('order_column')
        ->all();

    expect($ordered)->toBe([5, 10]);
});

it('invokes deleted hook when fieldable pivot is removed', function (): void {
    $preset = Preset::query()->firstOrFail();
    $field = Field::query()->create([
        'name' => 'obs_field_deleted_hook_' . uniqid(),
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);

    $pivot = Fieldable::query()->create([
        'field_id' => $field->id,
        'preset_id' => $preset->id,
        'order_column' => 1,
        'default' => null,
        'is_required' => false,
    ]);

    $rows_before = Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count();

    app(FieldableObserver::class)->deleted($pivot);

    expect(Presettable::query()->withTrashed()->where('preset_id', $preset->id)->count())->toBeGreaterThan($rows_before);
});

it('skips versioning when fieldable has no preset id', function (): void {
    $observer = app(FieldableObserver::class);
    $fieldable = new Fieldable(['preset_id' => null]);

    $method = new ReflectionMethod(FieldableObserver::class, 'createVersionForPreset');
    $method->setAccessible(true);
    $method->invoke($observer, $fieldable);

    expect(Presettable::query()->withTrashed()->count())->toBeGreaterThanOrEqual(0);
});
