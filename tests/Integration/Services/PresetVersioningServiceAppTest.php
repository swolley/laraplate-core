<?php

declare(strict_types=1);

use Modules\CMS\Casts\EntityType;
use Modules\CMS\Models\Entity;
use Modules\Core\Models\Preset;
use Modules\Core\Services\PresetVersioningService;

it('uses default core preset and entity classes on the base pivot', function (): void {
    $presettable = new class extends Modules\Core\Models\Pivot\Presettable {};
    $preset_method = new ReflectionMethod(Modules\Core\Models\Pivot\Presettable::class, 'presetModelClass');
    $entity_method = new ReflectionMethod(Modules\Core\Models\Pivot\Presettable::class, 'entityModelClass');

    expect($preset_method->invoke($presettable))->toBe(Preset::class)
        ->and($entity_method->invoke($presettable))->toBe(\Modules\Core\Models\Entity::class);
});

it('creates versions using the App presettable class for app-level presets', function (): void {
    $entity = Entity::query()->create([
        'name' => 'app_entity_' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $preset = \App\Models\Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'app_preset_' . uniqid(),
    ]);

    $service = resolve(PresetVersioningService::class);
    $version = $service->createVersion($preset);

    expect($version)->toBeInstanceOf(\App\Models\Pivot\Presettable::class)
        ->and($version->preset_id)->toBe($preset->id);
});

it('creates versions using the module presettable class for cms presets', function (): void {
    $entity = Entity::query()->create([
        'name' => 'cms_entity_' . uniqid(),
        'type' => EntityType::Contents,
    ]);

    $preset = \Modules\CMS\Models\Preset::query()->create([
        'entity_id' => $entity->id,
        'name' => 'cms_preset_' . uniqid(),
    ]);

    $service = resolve(PresetVersioningService::class);
    $version = $service->createVersion($preset);

    expect($version)->toBeInstanceOf(\Modules\CMS\Models\Pivot\Presettable::class)
        ->and($version->preset_id)->toBe($preset->id);
});
