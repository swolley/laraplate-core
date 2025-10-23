<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository as BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\Core\Helpers\ResponseBuilder;
use Override;
use Spatie\Permission\Models\Role;

/**
 * @template TDuration of Closure|DateInterval|DateTimeInterface|int|null
 */
final class Repository extends BaseRepository
{
    /**
     * Get the cache tags.
     */
    public function getCacheTags(array|string $tags = []): array
    {
        return array_merge([config('app.name')], is_string($tags) ? [$tags] : $tags);
    }

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

    /**
     * Try to extract from cache or by specified callback using request info.
     *
     * @template TCacheValue
     *
     * @param   Model|string|array<string|object>|null
     * @param  Closure(TCacheValue): mixed  $callback
     * @return TCacheValue
     */
    public function tryByRequest(Model|string|array|null $entity, Request $request, Closure $callback, ?int $duration = null): mixed
    {
        $tags = self::getCacheTags($entity);

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (! method_exists($model, 'usesCache') || ! $model->usesCache()) {
                    return $callback();
                }

                $tags[] = $this->getTableName($model);
            }
        }

        $user = $this->getKeyPartsFromUser($request->user());

        if ($user !== []) {
            array_push($tags, ...$user);
        }

        $key = $this->getKeyFromRequest($request);
        $duration ??= config('cache.duration');

        if ($this->has($key)) {
            return $this->get($key);
        }

        $data = $callback();

        if ($data instanceof ResponseBuilder) {
            $data = $data->getResponse();
        }

        $this->put($key, $data, $duration ?: config('cache.duration'));

        return $data;
    }

    /**
     * clear cache by specified entity.
     *
     * @param   Model|string|array<string|object>
     */
    public function clearByEntity(Model|string|array $entity): void
    {
        $models = Arr::wrap($entity);

        foreach ($models as &$model) {
            if (is_string($model)) {
                $model = new $model();
            }

            if (method_exists($model, 'usesCache') && $model->usesCache()) {
                $this->tags(self::getCacheTags($this->getTableName($model)))->flush();
            }
        }
    }

    /**
     * clear cache by request extracted info.
     *
     * @param  Model|string|array<string|object>|null
     */
    public function clearByRequest(Request $request, Model|string|array|null $entity = null): void
    {
        $key = $this->getKeyFromRequest($request);

        if ($entity) {
            $entity = Arr::wrap($entity);

            foreach ($entity as $model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (! method_exists($model, 'usesCache') || $model->usesCache()) {
                    $this->tags(self::getCacheTags($this->getTableName($model)))->forget($key);
                }
            }
        } else {
            $this->tags(self::getCacheTags())->forget($key);
        }
    }

    /**
     * clear cache elements by user and only by entity if specified.
     *
     * @param  Model|string|array<string|object>|null
     */
    public function clearByUser(User $user, Model|string|array|null $entity = null): void
    {
        $user_key = 'U' . $user->id;

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    $this->tags(self::getCacheTags([$this->getTableName($model), $user_key]))->flush();
                }
            }
        } else {
            $this->tags(self::getCacheTags($user_key))->flush();
        }
    }

    /**
     * clear cache elements by user group and only by entity if specified.
     *
     * @param  Model|string|array<string|object>|null
     */
    public function clearByGroup(Role $role, Model|string|array|null $entity = null): void
    {
        $role_key = 'R' . $role->id;

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    $this->tags(self::getCacheTags([$this->getTableName($model), $role_key]))->flush();
                }
            }
        } else {
            $this->tags(self::getCacheTags($role_key))->flush();
        }
    }

    /**
     * recursively sorts array by keys.
     *
     * @param  array<int,string>|string|null  $array
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
     * contruct a cache key by request info.
     */
    private function getKeyFromRequest(Request $request): string
    {
        $path = $request->getPathInfo();
        $params = $request->query();
        $user = $this->getKeyPartsFromUser($request->user());

        if ($user !== []) {
            self::recursiveKSort($params);
        }

        return base64_encode($path . ($user !== null && $user !== [] ? implode('_', $user) . '_' : '') . serialize($params));
    }

    private function getTableName(string|Model $entity): string
    {
        return is_string($entity) ? $entity : $entity->getTable();
    }

    /**
     * compose key parts by user and groups.
     *
     * @return string[]
     */
    private function getKeyPartsFromUser(User $user): array
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
            $groups = $user->{$group_method}->map(fn (Model $r): string => 'R' . $r->id)->toArray();
            sort($groups);
            array_push($tags, ...$groups);
        }

        return array_map('strval', $tags);
    }

    private function getDuration(): int|array
    {
        $threshold = $this->getThreshold();

        if ($threshold !== null && $threshold !== 0) {
            return [$threshold, config('cache.duration')];
        }

        return config('cache.duration');
    }

    private function getThreshold(): ?int
    {
        return config('cache.threshold');
    }
}
