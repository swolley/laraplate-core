<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use App\Casts\EntityType;
use App\Models\Entity;
use App\Models\Preset;
use Illuminate\Support\Str;
use Modules\Core\Helpers\HelpersCache;

/**
 * Core-owned dynamic entity fixtures, so Core tests do not need a module (CMS, ERP...) to have
 * an entity and a preset. The models live in the App namespace (Core's `stubs/App`), because
 * Core resolves a dynamic entity's concrete Entity, Preset and Presettable by module naming
 * convention, and a Core-namespaced fixture would resolve to Core's abstract classes.
 */
final class AppDynamicEntities
{
    private function __construct() {}

    /**
     * The entity of the given type, created on first use.
     */
    public static function entity(EntityType $type = EntityType::Pages): Entity
    {
        return Entity::query()->firstOrCreate(
            ['name' => $type->value],
            ['type' => $type, 'slug' => Str::slug($type->value)],
        );
    }

    /**
     * A preset of the given entity type, created (with its entity) on first use.
     */
    public static function preset(EntityType $type = EntityType::Pages, string $name = 'default'): Preset
    {
        $entity = self::entity($type);

        return Preset::query()->firstOrCreate(['entity_id' => $entity->id, 'name' => $name]);
    }

    /**
     * Add {@see Preset} to the discovered models for the rest of the test.
     *
     * The fixture lives in Core's stubs, outside the folders models() scans, so code that finds
     * presets through models() (e.g. FieldableObserver) cannot see it. An application keeps its
     * presets in app/Models, where they are discovered. Undo with {@see self::forgetDiscoveredPreset()}.
     */
    public static function discoverPreset(): void
    {
        HelpersCache::setModels('active', array_values(array_unique([...models(), Preset::class])));
    }

    /**
     * Drop the discovered models, so the next models() call scans again.
     */
    public static function forgetDiscoveredPreset(): void
    {
        HelpersCache::clearModels();
    }
}
