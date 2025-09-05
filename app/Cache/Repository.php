<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository as BaseRepository;
// use Illuminate\Support\Str;
use Override;

/**
 * @template TDuration of Closure|DateInterval|DateTimeInterface|int|null
 */
final class Repository extends BaseRepository
{
    use HasCacheRepository;

    private int $maxRetries = 3;
    private int $retryDelay = 100; // milliseconds

    #[Override]
    public function remember($key, $ttl, Closure $callback): mixed
    {
        $ttl ??= $this->getDuration();

        if (is_array($ttl)) {
            return parent::flexible($key, $ttl, $callback);
        }

        return parent::remember($key, $ttl, $callback);
    }

    // #[Override]
    // public function add($key, $value, $ttl = null)
    // {
    //     try {
    //         // Prova il metodo standard
    //         return parent::add($key, $value, $ttl);
    //     } catch (\RedisException $e) {
    //         // try to handle Dragonfly connections without LUA scripts enabled
    //         if (Str::endsWith($e->getFile(), 'PhpRedisConnector.php') && $e->getCode() === 0  && $e->getMessage() === 'Connection refused' && is_null($this->get($key))) {
    //             return $this->put($key, $value, $ttl);
    //         }
    //     }
    // }
}
