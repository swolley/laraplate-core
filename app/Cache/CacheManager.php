<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Cache\CacheManager as BaseCacheManager;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Application;

final class CacheManager extends BaseCacheManager
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Get a cache store instance by name. Returns the Core Repository for the default driver so that getCacheTags() and tag support are available.
     */
    public function store($name = null): RepositoryContract
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
