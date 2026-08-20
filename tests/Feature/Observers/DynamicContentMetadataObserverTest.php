<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\CMS\Casts\EntityType;
use Modules\CMS\Models\Entity;
use Modules\CMS\Models\Preset;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Field;
use Modules\Core\Services\DynamicContentsService;

beforeEach(function (): void {
    Cache::flush();
    DynamicContentsService::reset();
});

/**
 * The observer must invalidate both the in-memory and the persistent
 * `rememberForever` caches, so every assertion below fetches, mutates and
 * re-fetches through the SAME singleton instance without resetting it. Without
 * the observer wiring the second fetch returns the stale cached collection and
 * the expectation fails.
 */
it('invalidates cached entities when an entity is created', function (): void {
    Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $service = DynamicContentsService::getInstance();
    $before = $service->fetchAvailableEntities(EntityType::Contents)->count();

    Entity::query()->create([
        'name' => 'Page_' . uniqid(),
        'slug' => 'page-' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    expect($service->fetchAvailableEntities(EntityType::Contents)->count())->toBe($before + 1);
});

it('invalidates cached presets and presettables when a preset is created', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $service = DynamicContentsService::getInstance();
    $presets_before = $service->fetchAvailablePresets(EntityType::Contents)->count();
    $presettables_before = $service->fetchAvailablePresettables(EntityType::Contents)->count();

    Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'preset_' . uniqid(),
    ]);

    expect($service->fetchAvailablePresets(EntityType::Contents)->count())->toBe($presets_before + 1)
        ->and($service->fetchAvailablePresettables(EntityType::Contents)->count())->toBe($presettables_before + 1);
});

it('invalidates cached presets when a preset is updated', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'original_name',
    ]);

    $service = DynamicContentsService::getInstance();
    $service->fetchAvailablePresets(EntityType::Contents);

    $preset->update(['name' => 'renamed_name']);

    $names = $service->fetchAvailablePresets(EntityType::Contents)->pluck('name');

    expect($names)->toContain('renamed_name')
        ->and($names)->not->toContain('original_name');
});

it('invalidates cached presettables when a preset is deleted', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'preset_' . uniqid(),
    ]);

    $service = DynamicContentsService::getInstance();
    $before = $service->fetchAvailablePresettables(EntityType::Contents)->count();

    $preset->delete();

    expect($service->fetchAvailablePresettables(EntityType::Contents)->count())->toBe($before - 1);
});

it('invalidates cached preset fields when a linked field is updated', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'preset_' . uniqid(),
    ]);

    $field = Field::query()->create([
        'name' => 'field_original',
        'type' => FieldType::Text,
        'options' => new stdClass(),
    ]);

    $preset->fields()->attach($field->id, [
        'is_required' => false,
        'order_column' => 0,
        'default' => null,
    ]);

    $service = DynamicContentsService::getInstance();
    $service->fetchAvailablePresets(EntityType::Contents);

    $field->update(['name' => 'field_renamed']);

    $field_names = $service->fetchAvailablePresets(EntityType::Contents)
        ->firstWhere('id', $preset->id)
        ->fields
        ->pluck('name');

    expect($field_names)->toContain('field_renamed')
        ->and($field_names)->not->toContain('field_original');
});
