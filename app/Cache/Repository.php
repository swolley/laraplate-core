<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository as BaseRepository;
use Override;

/**
 * @template TTtl of Closure|DateInterval|DateTimeInterface|int|null
 */
final class Repository extends BaseRepository
{
    use HasCacheRepository;

    #[Override]
    /**
     * @param  TTtl|list<TTtl,TTtl>  $ttl
     */
    public function remember($key, $ttl, Closure $callback): mixed
    {
        $ttl ??= $this->getDuration();
        $method = is_array($ttl) ? 'flexible' : 'remember';

        return parent::$method($key, $ttl, $callback);
    }
}
