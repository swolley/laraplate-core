<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Inspector\Entities\Table;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\DynamicEntity;
use UnexpectedValueException;

/**
 * Singleton service that caches DynamicEntity instances during the request/command scope.
 * Schema inspection is delegated to SchemaInspector (shared in-memory cache).
 */
final class DynamicEntityService
{
    private static ?self $instance = null;

    /**
     * In-memory cache for resolved DynamicEntity instances.
     */
    private array $resolved_cache = [];

    /**
     * Cached config value for dynamic entities.
     */
    private ?bool $dynamic_entities_enabled = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function resolve(string $tableName, ?string $connection = null, $attributes = [], ?Request $request = null): DynamicEntity
    {
        $cache_key = sprintf('dynamic_entities.%s.%s', $connection ?? 'default', $tableName);

        // Check in-memory cache first
        if (isset($this->resolved_cache[$cache_key])) {
            $cached = $this->resolved_cache[$cache_key];

            // Clone to avoid sharing state
            return clone $cached;
        }

        $model = DynamicEntity::tryResolveModel($tableName, $connection);

        if (! in_array($model, [null, '', '0'], true)) {
            return new $model($attributes);
        }

        // Cache config value to avoid repeated calls
        if ($this->dynamic_entities_enabled === null) {
            $this->dynamic_entities_enabled = config('crud.dynamic_entities', false);
        }

        throw_unless($this->dynamic_entities_enabled, UnexpectedValueException::class, 'Dynamic tables mapping is not enabled');

        $resolved = Cache::remember($cache_key, null, function () use ($tableName, $connection, $attributes, $request): DynamicEntity {
            $model = new DynamicEntity($attributes);
            $model->inspect($tableName, $connection, $request);

            return $model;
        });

        // Store in memory
        $this->resolved_cache[$cache_key] = $resolved;

        // Clone to avoid sharing state
        return clone $resolved;
    }

    /**
     * Get inspected table data via SchemaInspector (shared in-memory cache).
     */
    public function getInspectedTable(string $tableName, ?string $connection = null): ?Table
    {
        return SchemaInspector::getInstance()->table($tableName, $connection);
    }

    public function clearCache(string $tableName, ?string $connection = null): void
    {
        $cache_key = sprintf('dynamic_entities.%s.%s', $connection ?? 'default', $tableName);
        unset($this->resolved_cache[$cache_key]);
        Cache::forget($cache_key);

        SchemaInspector::getInstance()->clearTable($tableName, $connection);
    }

    public function clearAllCaches(): void
    {
        $this->resolved_cache = [];
        SchemaInspector::getInstance()->clearAll();
    }
}
