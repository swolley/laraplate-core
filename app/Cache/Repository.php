<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Override;
use Illuminate\Cache\Repository as BaseRepository;

final class Repository extends BaseRepository
{
    use CacheRepositoryTrait;

    #[Override]
    public function remember($key, $ttl, \Closure $callback): mixed
    {
        $ttl ??= $this->getDuration();
        $method = is_array($ttl) ? 'flexible' : 'remember';

        return parent::$method($key, $ttl, $callback);
    }
}
