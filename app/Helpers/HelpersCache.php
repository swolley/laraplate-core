<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;

/**
 * Static in-memory cache for expensive helper functions (models, connections).
 * Cleared automatically after migrations via CommandListenerProvider.
 */
final class HelpersCache
{
    /**
     * @var array<string, list<class-string<Model>>>
     */
    private static array $models = [];

    /**
     * @var array<string, list<string>>
     */
    private static array $connections = [];

    private function __construct() {}

    /**
     * @return list<class-string<Model>>|null
     */
    public static function getModels(string $key): ?array
    {
        return self::$models[$key] ?? null;
    }

    /**
     * @param  list<class-string<Model>>  $models
     */
    public static function setModels(string $key, array $models): void
    {
        self::$models[$key] = $models;
    }

    /**
     * @return list<string>|null
     */
    public static function getConnections(string $key): ?array
    {
        return self::$connections[$key] ?? null;
    }

    /**
     * @param  list<string>  $connections
     */
    public static function setConnections(string $key, array $connections): void
    {
        self::$connections[$key] = $connections;
    }

    public static function clearModels(): void
    {
        self::$models = [];
    }

    public static function clearConnections(): void
    {
        self::$connections = [];
    }

    public static function clearAll(): void
    {
        self::$models = [];
        self::$connections = [];
    }
}
