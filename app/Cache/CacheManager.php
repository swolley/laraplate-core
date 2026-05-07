<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Cache\CacheManager as BaseCacheManager;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Application;

final class CacheManager extends BaseCacheManager
{
    /**
     * Cached app name to avoid repeated config calls.
     */
    private static ?string $app_name = null;

    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Generate a namespaced cache key in the format:
     * {app_name}:{namespace}:{part1}:{part2}:...
     *
     * This ensures all cache keys are prefixed with the application name,
     * preventing collisions in shared cache environments.
     */
    public static function key(string $namespace, string ...$parts): string
    {
        if (self::$app_name === null) {
            self::$app_name = (string) config('app.name');
        }

        $segments = array_merge([self::$app_name, $namespace], $parts);

        return implode(':', $segments);
    }

    /**
     * Reset the cached app name (used in tests or long-running processes).
     */
    public static function resetAppNameCache(): void
    {
        self::$app_name = null;
    }

    /**
     * Get a cache store instance by name. Returns the Core Repository for the default driver so that getCacheTags() and tag support are available.
     * 
     * @param  string|null  $name
     */
    public function store($name = null): RepositoryContract // @pest-ignore-type
    {
        $name ??= $this->getDefaultDriver();

        if ($name === $this->getDefaultDriver() && $this->app->bound('cache.store')) {
            $this->stores[$name] ??= $this->app->make(\Illuminate\Cache\Repository::class);

            return $this->stores[$name];
        }

        return parent::store($name);
    }

    /**
     * Create a custom CacheManager that returns our Repository.
     */
    public function repository(Store $store, array $config = []): Repository
    {
        return new Repository($store, $config);
    }
}
