<?php

declare(strict_types=1);

use App\Casts\EntityType;
use App\Models\Entity;
use App\Models\Preset;
use Illuminate\Support\Facades\Cache;
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
        'type' => EntityType::Pages,
    ]);

    $service = DynamicContentsService::getInstance();
    $before = $service->fetchAvailableEntities(EntityType::Pages)->count();

    Entity::query()->create([
        'name' => 'Page_' . uniqid(),
        'slug' => 'page-' . uniqid(),
        'type' => EntityType::Pages,
    ]);

    expect($service->fetchAvailableEntities(EntityType::Pages)->count())->toBe($before + 1);
});

it('invalidates cached presets and presettables when a preset is created', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Pages,
    ]);

    $service = DynamicContentsService::getInstance();
    $presets_before = $service->fetchAvailablePresets(EntityType::Pages)->count();
    $presettables_before = $service->fetchAvailablePresettables(EntityType::Pages)->count();

    Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'preset_' . uniqid(),
    ]);

    expect($service->fetchAvailablePresets(EntityType::Pages)->count())->toBe($presets_before + 1)
        ->and($service->fetchAvailablePresettables(EntityType::Pages)->count())->toBe($presettables_before + 1);
});

/**
 * Parallel BatchSeeder workers call {@see DynamicContentsService::reset()} after fork,
 * which drops the in-process memo-key registry. Invalidation must still bust typed
 * persistent keys (via metadata generation), otherwise a stale presets list can miss
 * a presettable's preset_id ("No cached preset [N]").
 */
it('invalidates typed preset memo keys even after DynamicContentsService::reset', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Pages,
    ]);

    // Warm an empty (or partial) presets list, then drop the in-process registry like a fork.
    DynamicContentsService::getInstance()->fetchAvailablePresets(EntityType::Pages);
    DynamicContentsService::reset();

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'preset_after_reset_' . uniqid(),
    ]);

    $ids = DynamicContentsService::getInstance()
        ->fetchAvailablePresets(EntityType::Pages)
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    expect($ids)->toContain((int) $preset->id);
});

it('invalidates cached presets when a preset is updated', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Pages,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'original_name',
    ]);

    $service = DynamicContentsService::getInstance();
    $service->fetchAvailablePresets(EntityType::Pages);

    $preset->update(['name' => 'renamed_name']);

    $names = $service->fetchAvailablePresets(EntityType::Pages)->pluck('name');

    expect($names)->toContain('renamed_name')
        ->and($names)->not->toContain('original_name');
});

it('invalidates cached presettables when a preset is deleted', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Pages,
    ]);

    $preset = Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'preset_' . uniqid(),
    ]);

    $service = DynamicContentsService::getInstance();
    $before = $service->fetchAvailablePresettables(EntityType::Pages)->count();

    $preset->delete();

    expect($service->fetchAvailablePresettables(EntityType::Pages)->count())->toBe($before - 1);
});

it('invalidates cached preset fields when a linked field is updated', function (): void {
    $entity = Entity::query()->create([
        'name' => 'Article_' . uniqid(),
        'slug' => 'article-' . uniqid(),
        'type' => EntityType::Pages,
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
    $service->fetchAvailablePresets(EntityType::Pages);

    $field->update(['name' => 'field_renamed']);

    $field_names = $service->fetchAvailablePresets(EntityType::Pages)
        ->firstWhere('id', $preset->id)
        ->fields
        ->pluck('name');

    expect($field_names)->toContain('field_renamed')
        ->and($field_names)->not->toContain('field_original');
});
