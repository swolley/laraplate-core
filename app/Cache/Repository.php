<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\ResponseBuilder;
use Illuminate\Cache\Repository as BaseRepository;

class Repository extends BaseRepository
{
    private function getDuration(): int|array
    {
        if ($threshold = $this->getThreshold()) {
            return [$threshold, config('cache.duration')];
        }

        return config('cache.duration');
    }

    private function getThreshold(): int|null
    {
        return config('cache.threshold');
    }

    #[\Override]
    public function remember($key, $ttl, \Closure $callback): mixed
    {
        $ttl ??= $this->getDuration();
        $method = is_array($ttl) ? 'flexible' : 'remember';

        return parent::$method($key, $ttl, $callback);
    }

    /**
     * contruct a cache key by request info
     */
    private static function getKeyFromRequest(Request $request): string
    {
        $path = $request->getPathInfo();
        $params = $request->query();
        $user = self::getKeyPartsFromUser($request->user());
        if ($user) {
            self::recursiveKSort($params);
        }

        return base64_encode($path . ($user ? implode('_', $user) . '_' : '') . serialize($params));
    }

    /**
     * Try to extract from cache or by specified callback using request info
     * 
     * @template TCacheValue
     * @param Model|string|array|null $entity
     * @param Request $request
     * @param Closure(TCacheValue): mixed $callback
     * @param int|null $duration
     * @return TCacheValue
     */
    final public function tryByRequest(Model|string|array|null $entity, Request $request, \Closure $callback, ?int $duration = null): mixed
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
     * clear cache by specified entity
     */
    final public function clearByEntity(Model|string|array $entity): void
    {
        $models = Arr::wrap($entity);

        foreach ($models as &$model) {
            if (is_string($model)) {
                $model = new $model();
            }

            if (method_exists($model, 'usesCache') && $model->usesCache()) {
                $this->tags([config('app.name'), self::getTableName($model)])->flush();
            }
        }
    }

    /**
     * clear cache by request extracted info
     */
    final public function clearByRequest(Request $request, Model|string|array|null $entity = null): void
    {
        $key = static::getKeyFromRequest($request);
        if ($entity) {
            $entity = Arr::wrap($entity);

            foreach ($entity as $model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (!method_exists($model, 'usesCache') || $model->usesCache()) {
                    $this->tags([config('app.name'), self::getTableName($model)])->forget($key);
                }
            }
        } else {
            $this->tags([config('app.name')])->forget($key);
        }
    }

    /**
     * clear cache elements by user and only by entity if specified
     */
    final public function clearByUser(User $user, Model|string|array|null $entity = null): void
    {
        $user_key = 'U' . $user->id;
        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    $this->tags([config('app.name'), self::getTableName($model), $user_key])->flush();
                }
            }
        } else {
            $this->tags([config('app.name'), $user_key])->flush();
        }
    }

    /**
     * clear cache elements by user group and only by entity if specified
     */
    final public function clearByGroup(Role $role, Model|string|array|null $entity = null): void
    {
        $role_key = 'R' . $role->id;
        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as &$model) {
                if (is_string($model)) {
                    $model = new $model();
                }

                if (method_exists($model, 'usesCache') && $model->usesCache()) {
                    $this->tags([config('app.name'), self::getTableName($model), $role_key])->flush();
                }
            }
        } else {
            $this->tags([config('app.name'), $role_key])->flush();
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
     * @return array<int,string>|null
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

        return array_map('strval', $tags);
    }
}
