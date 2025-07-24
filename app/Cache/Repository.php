<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository as BaseRepository;
use Override;

/**
 * @template TDuration of Closure|DateInterval|DateTimeInterface|int|null
 */
final class Repository extends BaseRepository
{
    use HasCacheRepository;

    #[Override]
    public function remember($key, $ttl, Closure $callback): mixed
    {
        $ttl ??= $this->getDuration();
        if (is_array($ttl)) {
            return parent::flexible($key, $ttl, $callback);
        }
        return parent::remember($key, $ttl, $callback);
    }
}
