<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\ResponseBuilder;
use Illuminate\Cache\Repository;

class CacheManager
{
    public static function getCurrentDriver(): string
    {
        $store = Cache::store()->getStore();

        return match (true) {
            $store instanceof \Illuminate\Cache\RedisStore => 'redis',
            $store instanceof \Illuminate\Cache\DatabaseStore => 'database',
            $store instanceof \Illuminate\Cache\FileStore => 'file',
            $store instanceof \Illuminate\Cache\MemcachedStore => 'memcached',
            $store instanceof \Illuminate\Cache\ArrayStore => 'array',
            default => 'unknown'
        };
    }

    public static function supportsTagging(): bool
    {
        return Cache::store()->getStore() instanceof \Illuminate\Contracts\Cache\Store;
    }

    /**
     * contruct a cache key by request info
     */
    public static function getKeyFromRequest(Request $request): string
    {
        $path = $request->getPathInfo();
        $params = $request->query();
        $user = self::getKeyPartsFromUser($request->user());
        self::recursiveKSort($params);

        return base64_encode($path . ($user ? implode('_', $user) . '_' : '') . serialize($params));
    }

    /**
     * Try to extract from cache or by specified callback using request info
     */
    public static function tryByRequest(Model|string|array|null $entity, Request $request, Closure $callback, ?int $duration = null, ?Repository $cache = null): mixed
    {
        $tags = [config('app.name')];
        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (!method_exists($model, 'usesCache') || !$model->usesCache()) {
                    return $callback();
                }
                $tags[] = self::getTableName($model);
            }
        }

        if ($user = self::getKeyPartsFromUser($request->user())) {
            array_push($tags, ...$user);
        }
        $key = static::getKeyFromRequest($request);
        $cache = $cache instanceof \Illuminate\Cache\Repository ? $cache->tags($tags) : Cache::tags($tags);
        $duration ??= config('cache.duration');

        if ($cache->has($key)) {
            return $cache->get($key);
        }

        $data = $callback();
        if ($data instanceof ResponseBuilder) {
            $data = $data->getResponse();
        }

        $cache->put($key, $data, $duration ?: config('cache.duration'));

        return $data;
    }

    /**
     * clear cache by specified entity
     */
    public static function clearByEntity(Model|string|array $entity, ?Repository $cache = null): void
    {
        $models = Arr::wrap($entity);

        foreach ($models as &$model) {
            if (is_string($model)) {
                $model = new $model();
            }

            if (method_exists($model, 'usesCache') && $model->usesCache()) {
                ($cache instanceof \Illuminate\Cache\Repository
                    ? $cache->tags([config('app.name'), self::getTableName($model)])
                    : Cache::tags([config('app.name'), self::getTableName($model)]))->flush();
            }
        }
    }

    /**
     * clear cache by request extracted info
     */
    public static function clearByRequest(Request $request, Model|string|array|null $entity = null, ?Repository $cache = null): void
    {
        $key = static::getKeyFromRequest($request);
        if ($entity) {
            $entity = Arr::wrap($entity);

            foreach ($entity as $model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (!method_exists($model, 'usesCache') || $model->usesCache()) {
                    ($cache instanceof \Illuminate\Cache\Repository
                        ? $cache->tags([config('app.name'), self::getTableName($model)])
                        : Cache::tags([config('app.name'), self::getTableName($model)]))->forget($key);
                }
            }
        } else {
            ($cache instanceof \Illuminate\Cache\Repository
                ? $cache->tags([config('app.name')])
                : Cache::tags([config('app.name')]))->forget($key);
        }
    }

    /**
     * clear cache elements by user and only by entity if specified
     */
    public static function clearByUser(User $user, Model|string|array|null $entity = null, ?Repository $cache = null): void
    {
        $user_key = 'U' . $user->id;
        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    ($cache instanceof \Illuminate\Cache\Repository
                        ? $cache->tags([config('app.name'), self::getTableName($model), $user_key])
                        : Cache::tags([config('app.name'), self::getTableName($model), $user_key]))->flush();
                }
            }
        } else {
            ($cache instanceof \Illuminate\Cache\Repository
                ? $cache->tags([config('app.name'), $user_key])
                : Cache::tags([config('app.name'), $user_key]))->flush();
        }
    }

    /**
     * clear cache elements by user group and only by entity if specified
     */
    public static function clearByGroup(Role $role, Model|string|array|null $entity = null, ?Repository $cache = null): void
    {
        $role_key = 'R' . $role->id;
        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    ($cache instanceof \Illuminate\Cache\Repository
                        ? $cache->tags([config('app.name'), self::getTableName($model), $role_key])
                        : Cache::tags([config('app.name'), self::getTableName($model), $role_key]))->flush();
                }
            }
        } else {
            ($cache instanceof \Illuminate\Cache\Repository
                ? $cache->tags([config('app.name'), $role_key])
                : Cache::tags([config('app.name'), $role_key]))->flush();
        }
    }

    private static function getTableName(string|Model $entity): string
    {
        return is_string($entity) ? $entity : $entity->getTable();
    }

    /** 
     * recursively sorts array by keys
     */
    private static function recursiveKSort(array|string|null &$array): void
    {
        if (is_array($array)) {
            ksort($array);

            foreach ($array as &$value) {
                self::recursiveKSort($value);
            }
        }
    }

    /**
     * compose key parts by user and groups
     * 
     * @return null|(mixed|string)[]
     * @psalm-return list{0?: mixed|string,...}|null
     */
    private static function getKeyPartsFromUser(User $user): ?array
    {
        $tags = ['U' . $user->id];
        $group_method = null;

        if (method_exists($user, 'groups')) {
            $group_method = 'groups';
        } elseif (method_exists($user, 'user_groups')) {
            $group_method = 'user_groups';
        } elseif (method_exists($user, 'roles')) {
            $group_method = 'roles';
        } elseif (method_exists($user, 'user_roles')) {
            $group_method = 'user_roles';
        }

        if ($group_method) {
            $groups = $user->{$group_method}->map(fn(Model $r): string => 'R' . (int) $r->id)->toArray();
            sort($groups);
            array_push($tags, ...$groups);
        }

        return $tags;
    }
}
