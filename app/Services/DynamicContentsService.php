<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use UnexpectedValueException;

/**
 * Singleton service that caches dynamic contents data in-memory during the request/command scope.
 * This prevents redundant cache access when the same data is requested multiple times.
 */
final class DynamicContentsService
{
    /**
     * Shared generation embedded in every typed memo key. Bumped on metadata
     * writes so invalidation works even after {@see self::reset()} drops the
     * in-process key registry (parallel BatchSeeder forks) or when another
     * process never warmed the registry — without this, stale
     * `rememberForever` preset lists miss new preset ids ("No cached preset [N]").
     */
    private const string METADATA_GENERATION_KEY = 'core.dynamic_contents.generation';

    /**
     * Memo / persistent cache keys used for presettable lists (one per concrete Presettable class).
     *
     * @var list<string>
     */
    private static array $registered_presettable_memo_keys = [];

    /**
     * Memo / persistent cache keys used for entity lists (one per dynamic content type).
     *
     * @var list<string>
     */
    private static array $registered_entity_memo_keys = [];

    /**
     * Memo / persistent cache keys used for preset lists (one per dynamic content type).
     *
     * @var list<string>
     */
    private static array $registered_preset_memo_keys = [];

    /**
     * Singleton instance.
     */
    private static ?self $instance = null;

    /**
     * In-memory cache for entities.
     *
     * @var array<string, Collection<int, Entity>>
     */
    private array $entities_cache = [];

    /**
     * In-memory cache for presets.
     *
     * @var array<string, Collection<int, Preset>>
     */
    private array $presets_cache = [];

    /**
     * In-memory cache for presettables.
     *
     * @var array<string, Collection<int, Presettable>>
     */
    private array $presettables_cache = [];

    /**
     * Private constructor to enforce singleton pattern.
     */
    private function __construct() {}

    /**
     * Get service instance (singleton pattern).
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Reset the singleton instance (useful for testing or cache invalidation).
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$registered_presettable_memo_keys = [];
        self::$registered_entity_memo_keys = [];
        self::$registered_preset_memo_keys = [];
    }

    /**
     * Fetch available entities for a given type.
     * Uses in-memory cache first, then external cache, then database.
     *
     * @return Collection<Entity>
     */
    public function fetchAvailableEntities(IDynamicEntityTypable $type): Collection
    {
        $type_cache_key = $this->typeCacheKey($type);

        if (isset($this->entities_cache[$type_cache_key])) {
            return $this->entities_cache[$type_cache_key];
        }

        $type_module = class_module($type);
        $entity_module = class_module(Entity::class);
        $entity_class = Str::replace("\\{$entity_module}\\", "\\{$type_module}\\", Entity::class);

        $entity_model = new $entity_class();
        $cache_key = $this->typedMemoKey($entity_model->getCacheKey(), $type_cache_key);
        $this->registerEntityMemoKey($cache_key);

        $this->entities_cache[$type_cache_key] = $this->rememberForeverCollection(
            $cache_key,
            fn (): Collection => $entity_class::query()
                ->withoutGlobalScopes()
                ->where('type', $type->toScalar())
                ->orderBy('is_default', 'desc')
                ->orderBy('name', 'asc')
                ->get(),
        );

        return $this->entities_cache[$type_cache_key];
    }

    /**
     * Fetch available presets for a given type.
     * Uses in-memory cache first, then external cache, then database.
     *
     * @return Collection<Preset>
     */
    public function fetchAvailablePresets(IDynamicEntityTypable $type): Collection
    {
        $type_cache_key = $this->typeCacheKey($type);

        if (isset($this->presets_cache[$type_cache_key])) {
            return $this->presets_cache[$type_cache_key];
        }

        $preset_class = $this->getModuleModelClass($type::class, Preset::class);
        $preset_model = new $preset_class();
        $cache_key = $this->typedMemoKey($preset_model->getCacheKey(), $type_cache_key);
        $this->registerPresetMemoKey($cache_key);

        $this->presets_cache[$type_cache_key] = $this->rememberForeverCollection(
            $cache_key,
            fn (): Collection => $preset_class::query()
                ->withoutGlobalScopes()
                ->with(['fields', 'entity'])
                ->whereHas('entity', static function (Builder $query) use ($type): void {
                    $query->where($query->qualifyColumn('type'), $type->toScalar());
                })
                ->orderBy('is_default', 'desc')
                ->orderBy('name', 'asc')
                ->get(),
        );

        return $this->presets_cache[$type_cache_key];
    }

    /**
     * Fetch available presettables for a given type.
     * Uses in-memory cache first, then external cache, then database.
     *
     * @return Collection<Presettable>
     */
    public function fetchAvailablePresettables(IDynamicEntityTypable $type): Collection
    {
        $type_cache_key = $this->typeCacheKey($type);

        if (isset($this->presettables_cache[$type_cache_key])) {
            return $this->presettables_cache[$type_cache_key];
        }

        $presettable_class = $this->getModuleModelClass($type::class, Presettable::class);

        // Namespaced key (table name alone collides with unrelated cache entries and shared Redis DBs).
        $cache_key = $this->presettableMemoKey($presettable_class, $type);
        $this->registerPresettableMemoKey($cache_key);

        $presettables_table = CoreTables::Presettables->value;
        $presets_table = CoreTables::Presets->value;
        $entities_table = CoreTables::Entities->value;

        $this->presettables_cache[$type_cache_key] = $this->rememberForeverCollection(
            $cache_key,
            fn (): Collection => $presettable_class::query()
                ->join($presets_table, "{$presettables_table}.preset_id", '=', "{$presets_table}.id")
                ->join($entities_table, "{$presets_table}.entity_id", '=', "{$entities_table}.id")
                ->whereNull("{$presettables_table}.deleted_at")
                ->whereNull("{$presets_table}.deleted_at")
                ->where("{$entities_table}.type", $type->toScalar())
                ->addSelect("{$presettables_table}.*", DB::raw("CASE WHEN {$presets_table}.is_default THEN 1 ELSE 0 END + CASE WHEN {$entities_table}.is_default THEN 1 ELSE 0 END as order_score"))
                ->orderBy('order_score', 'desc')
                ->get(),
        );

        return $this->presettables_cache[$type_cache_key];
    }

    /**
     * Clear in-memory cache for entities.
     * Should be called when entities are modified.
     */
    public function clearEntitiesCache(): void
    {
        $this->entities_cache = [];
        $this->forgetRegisteredMemoKeys(self::$registered_entity_memo_keys);
        self::forgetMemoCacheKey('entities');
        $this->bumpMetadataGeneration();
    }

    /**
     * Clear in-memory cache for presets.
     * Should be called when presets are modified.
     */
    public function clearPresetsCache(): void
    {
        $this->presets_cache = [];
        $this->forgetRegisteredMemoKeys(self::$registered_preset_memo_keys);
        self::forgetMemoCacheKey('presets');
        $this->bumpMetadataGeneration();
    }

    /**
     * Clear in-memory cache for presettables.
     * Should be called when presettables are modified.
     */
    public function clearPresettablesCache(): void
    {
        $this->presettables_cache = [];
        $this->forgetRegisteredMemoKeys(self::$registered_presettable_memo_keys);
        self::forgetMemoCacheKey('presettables');
        $this->bumpMetadataGeneration();
    }

    /**
     * Clear all in-memory caches.
     */
    public function clearAllCaches(): void
    {
        $this->entities_cache = [];
        $this->presets_cache = [];
        $this->presettables_cache = [];
        $this->forgetRegisteredMemoKeys(self::$registered_entity_memo_keys);
        $this->forgetRegisteredMemoKeys(self::$registered_preset_memo_keys);
        $this->forgetRegisteredMemoKeys(self::$registered_presettable_memo_keys);
        self::forgetMemoCacheKey('entities');
        self::forgetMemoCacheKey('presets');
        self::forgetMemoCacheKey('presettables');
        $this->bumpMetadataGeneration();
    }

    /**
     * Laravel's memoized cache layer keeps values in process memory; `cache:clear` only flushes
     * the underlying store, so stale memo entries must be forgotten explicitly.
     */
    private static function forgetMemoCacheKey(string $key): void
    {
        Cache::forget($key);
        Cache::memo()->forget($key);
    }

    /**
     * @param  list<string>  $in_memory_keys
     */
    private function forgetRegisteredMemoKeys(array &$in_memory_keys): void
    {
        foreach ($in_memory_keys as $key) {
            self::forgetMemoCacheKey($key);
        }

        $in_memory_keys = [];
    }

    /**
     * Remember a value forever via Cache::memo(), guaranteeing an Eloquent Collection.
     *
     * Persistent drivers (Redis serializers, failover, fork reconnect) can rehydrate
     * Eloquent collections as plain arrays. Laravel's rememberForever treats any
     * non-null value as a hit, so a cached array would bypass the loader and violate
     * our return type — reload from the database when that happens.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  callable(): Collection<int, TModel>  $loader
     * @return Collection<int, TModel>
     */
    private function rememberForeverCollection(string $cache_key, callable $loader): Collection
    {
        $cached = Cache::memo()->rememberForever($cache_key, $loader);

        if ($cached instanceof Collection) {
            return $cached;
        }

        $fresh = $loader();
        Cache::memo()->forever($cache_key, $fresh);

        return $fresh;
    }

    /**
     * Get the module model class for a given local and target class.
     *
     * @param  class-string  $local_class  The local class
     * @param  class-string  $target_class  The target class
     * @return class-string The module model class
     */
    private function getModuleModelClass(string $local_class, string $target_class): string
    {
        $from_module = class_module($local_class);
        $to_module = class_module($target_class);
        $search_prefix = $to_module === 'App'
            ? $to_module . '\\'
            : 'Modules\\' . $to_module . '\\';
        $replace_prefix = $from_module === 'App'
            ? $from_module . '\\'
            : 'Modules\\' . $from_module . '\\';
        $pattern = '#^' . preg_quote($search_prefix, '#') . '#';
        $replaced = (string) preg_replace($pattern, $replace_prefix, $target_class, 1);

        throw_if(! class_exists($replaced), UnexpectedValueException::class, "Target class not found: {$replaced}");

        return $replaced;
    }

    private function typeCacheKey(IDynamicEntityTypable $type): string
    {
        return $type::class . ':' . $type->toScalar();
    }

    private function metadataGeneration(): int
    {
        return (int) Cache::get(self::METADATA_GENERATION_KEY, 0);
    }

    private function bumpMetadataGeneration(): void
    {
        // Use the non-memo store only. Cache::memo()->forget() also deletes from the
        // underlying repository, which would wipe the generation we just wrote.
        Cache::forever(self::METADATA_GENERATION_KEY, $this->metadataGeneration() + 1);
    }

    private function typedMemoKey(string $cache_key, string $type_cache_key): string
    {
        return $cache_key . ':' . hash('sha256', $type_cache_key) . ':g' . $this->metadataGeneration();
    }

    /**
     * @param  class-string<Presettable>  $presettable_class
     */
    private function presettableMemoKey(string $presettable_class, ?IDynamicEntityTypable $type = null): string
    {
        $key_parts = $type instanceof IDynamicEntityTypable
            ? $presettable_class . ':' . $this->typeCacheKey($type)
            : $presettable_class;

        return 'core.dynamic_contents.presettables:' . hash('sha256', $key_parts) . ':g' . $this->metadataGeneration();
    }

    private function registerEntityMemoKey(string $cache_key): void
    {
        if (! in_array($cache_key, self::$registered_entity_memo_keys, true)) {
            self::$registered_entity_memo_keys[] = $cache_key;
        }
    }

    private function registerPresetMemoKey(string $cache_key): void
    {
        if (! in_array($cache_key, self::$registered_preset_memo_keys, true)) {
            self::$registered_preset_memo_keys[] = $cache_key;
        }
    }

    private function registerPresettableMemoKey(string $cache_key): void
    {
        if (! in_array($cache_key, self::$registered_presettable_memo_keys, true)) {
            self::$registered_presettable_memo_keys[] = $cache_key;
        }
    }
}
