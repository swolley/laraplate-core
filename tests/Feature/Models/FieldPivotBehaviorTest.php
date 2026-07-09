<?php

declare(strict_types=1);

use Modules\CMS\Models\Preset;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Overrides\Model;

beforeEach(function (): void {
    setupCMSEntities();
});

it('reads and writes pivot attributes through field accessors', function (): void {
    $preset = Preset::query()->firstOrFail();
    $field = Field::query()->create([
        'name' => 'pivot_field_' . uniqid(),
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);

    $preset->fields()->attach($field->id, [
        'order_column' => 99,
        'is_required' => true,
        'default' => ['placeholder' => 'x'],
    ]);

    $field_table = (new Field())->getTable();
    $field_with_pivot = $preset->fields()->where($field_table . '.id', $field->id)->firstOrFail();

    expect($field_with_pivot->pivot)->toBeInstanceOf(Fieldable::class)
        ->and($field_with_pivot->pivot->is_required)->toBeTrue();

    $field_model = new class extends Field
    {
        public Fieldable $pivot;
    };
    $field_model->pivot = $field_with_pivot->pivot;

    expect($field_model->getAttribute('field_id'))->toBe($field->id);

    $field_model->setAttribute('order_column', 42);
    expect($field_model->pivot->order_column)->toBe(42);

    expect($field_model->toArray())->toHaveKey('is_required');
});

it('extends validation rules for create and update operations', function (): void {
    $field = Field::query()->create([
        'name' => 'rules_field_' . uniqid(),
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);

    $rules = $field->getRules();
    expect($rules['create'])->toHaveKey('name')
        ->and($rules[Model::DEFAULT_RULE])->toHaveKey('type')
        ->and($rules['update'])->toHaveKey('name');
});
