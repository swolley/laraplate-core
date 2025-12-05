<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Cache\CacheManager as BaseCacheManager;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Foundation\Application;

final class CacheManager extends BaseCacheManager
{
    public function __construct(Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Create a custom CacheManager that returns our Repository.
     */
    public function repository(Store $store, array $config = []): Repository
    {
        return new Repository($store, $config);
    }
}
