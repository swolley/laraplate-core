<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\Repository as BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Models\User;
use Override;
use Spatie\Permission\Models\Role;

/**
 * @template TDuration of Closure|DateInterval|DateTimeInterface|int|array<int|string, mixed>|null
 */
final class Repository extends BaseRepository
{
    /**
     * Cached app name to avoid repeated config calls.
     */
    private static ?string $app_name = null;

    /**
     * @param  array<string>|string  $tags
     * @return array<string>
     */
    public function getCacheTags(array|string $tags = []): array
    {
        if (self::$app_name === null) {
            $app_name = config('app.name');
            self::$app_name = is_string($app_name) ? $app_name : '';
        }

        return array_merge([self::$app_name], is_string($tags) ? [$tags] : $tags);
    }

    #[Override]
    /**
     * @param  string  $key
     * @param  int|array<int, DateInterval|DateTimeInterface|int|string>|null  $ttl  List of two items uses Laravel {@see parent::flexible()}; other arrays are normalized to seconds (e.g. named durations from config).
     * @template TCacheValue
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember($key, $ttl, Closure $callback): mixed // @pest-ignore-type
    {
        return $this->executeRemember($key, $ttl, $callback);
    }

    /**
     * @template TCacheValue
     *
     * @param  string|\UnitEnum  $key
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    private function executeRemember(string|\UnitEnum $key, mixed $ttl, Closure $callback): mixed
    {
        if (is_array($ttl)) {
            if (count($ttl) === 2 && is_numeric($ttl[0] ?? null) && is_numeric($ttl[1] ?? null)) {
                return parent::flexible($key, [(int) $ttl[0], (int) $ttl[1]], $callback);
            }

            return parent::remember($key, $this->resolveDurationConfigToSeconds($ttl), $callback);
        }

        if ($ttl === null) {
            $threshold = $this->getThreshold();
            $base_seconds = $this->resolveDefaultCacheSeconds();

            if ($threshold !== null && $threshold !== 0) {
                return parent::flexible($key, [$threshold, $base_seconds], $callback);
            }

            return parent::remember($key, $base_seconds, $callback);
        }

        if (is_int($ttl) || $ttl instanceof DateInterval || $ttl instanceof DateTimeInterface || $ttl instanceof Closure) {
            return parent::remember($key, $ttl, $callback);
        }

        if (is_numeric($ttl)) {
            return parent::remember($key, (int) $ttl, $callback);
        }

        return parent::remember($key, $this->resolveDefaultCacheSeconds(), $callback);
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
     * @template TCacheValue
     *
     * @param  Model|class-string<Model>|array<Model|class-string<Model>>|null  $entity
     * @param  Closure(): (TCacheValue|ResponseBuilder)  $callback
     * @return TCacheValue|JsonResponse
     */
    public function tryByRequest(Model|string|array|null $entity, Request $request, Closure $callback, ?int $duration = null): mixed
    {
        $tags_input = $entity instanceof Model
            ? $this->getTableName($entity)
            : (is_array($entity) ? array_map(fn (Model|string $e): string => $e instanceof Model ? $this->getTableName($e) : $e, $entity) : ($entity ?? []));
        $tags = self::getCacheTags(is_array($tags_input) ? $tags_input : $tags_input);

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as $model) {
                $resolved_model = $this->resolveModel($model);

                if ($resolved_model === null) {
                    return $this->unwrapCallbackResult($callback());
                }

                if (! method_exists($resolved_model, 'usesCache') || ! $resolved_model->usesCache()) {
                    return $this->unwrapCallbackResult($callback());
                }

                $tags[] = $this->getTableName($resolved_model);
            }
        }

        $request_user = $request->user();
        $user = $request_user instanceof User ? $request_user : null;
        $user_tags = $this->getKeyPartsFromUser($user);

        if ($user_tags !== []) {
            array_push($tags, ...$user_tags);
        }

        $key = $this->getKeyFromRequest($request, $user_tags);
        $ttl = $duration ?? $this->resolveDefaultCacheSeconds();

        return $this->remember($key, $ttl, fn () => $this->unwrapCallbackResult($callback()));
    }

    /**
     * @template TCacheValue
     *
     * @param  TCacheValue|ResponseBuilder  $data
     * @return TCacheValue|JsonResponse
     */
    private function unwrapCallbackResult(mixed $data): mixed
    {
        if ($data instanceof ResponseBuilder) {
            return $data->getResponse();
        }

        return $data;
    }

    /**
     * @param  Model|class-string<Model>|mixed  $model
     */
    private function resolveModel(mixed $model): ?Model
    {
        if ($model instanceof Model) {
            return $model;
        }

        if (! is_string($model) || $model === '') {
            return null;
        }

        $instance = new $model();

        return $instance instanceof Model ? $instance : null;
    }

    /**
     * Clear cache by the specified entity.
     *
     * @param  Model|class-string<Model>|array<Model|class-string<Model>>  $entity
     */
    public function clearByEntity(Model|string|array $entity): void
    {
        $models = Arr::wrap($entity);

        foreach ($models as $model) {
            $resolved_model = $this->resolveModel($model);

            if ($resolved_model === null) {
                continue;
            }

            if (method_exists($resolved_model, 'usesCache') && $resolved_model->usesCache()) {
                $this->tags(self::getCacheTags($this->getTableName($resolved_model)))->flush();
            }
        }
    }

    /**
     * clear cache by request extracted info.
     *
     * @param  Model|class-string<Model>|array<Model|class-string<Model>>|null  $entity
     */
    public function clearByRequest(Request $request, Model|string|array|null $entity = null): void
    {
        $request_user = $request->user();
        $user = $request_user instanceof User ? $request_user : null;
        $key = $this->getKeyFromRequest($request, $this->getKeyPartsFromUser($user));

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as $model) {
                $resolved_model = $this->resolveModel($model);

                if ($resolved_model === null) {
                    continue;
                }

                if (! method_exists($resolved_model, 'usesCache') || $resolved_model->usesCache()) {
                    $this->tags(self::getCacheTags($this->getTableName($resolved_model)))->forget($key);
                }
            }
        } else {
            $this->tags(self::getCacheTags())->forget($key);
        }
    }

    /**
     * clear cache elements by user and only by entity if specified.
     *
     * @param  Model|class-string<Model>|array<Model|class-string<Model>>|null  $entity
     */
    public function clearByUser(User $user, Model|string|array|null $entity = null): void
    {
        $user_key = 'U' . $user->id;

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as $model) {
                $resolved_model = $this->resolveModel($model);

                if ($resolved_model === null) {
                    continue;
                }

                if (method_exists($resolved_model, 'usesCache') && $resolved_model->usesCache()) {
                    $this->tags(self::getCacheTags([$this->getTableName($resolved_model), $user_key]))->flush();
                }
            }
        } else {
            $this->tags(self::getCacheTags($user_key))->flush();
        }
    }

    /**
     * clear cache elements by user group and only by entity if specified.
     *
     * @param  Model|class-string<Model>|array<Model|class-string<Model>>|null  $entity
     */
    public function clearByGroup(Role $role, Model|string|array|null $entity = null): void
    {
        $role_key = 'R' . $role->id;

        if ($entity) {
            $models = Arr::wrap($entity);

            foreach ($models as $model) {
                $resolved_model = $this->resolveModel($model);

                if ($resolved_model === null) {
                    continue;
                }

                if (method_exists($resolved_model, 'usesCache') && $resolved_model->usesCache()) {
                    $this->tags(self::getCacheTags([$this->getTableName($resolved_model), $role_key]))->flush();
                }
            }
        } else {
            $this->tags(self::getCacheTags($role_key))->flush();
        }
    }

    /**
     * recursively sorts array by keys.
     *
     * @param  array<int|string, mixed>|string|null  $array
     */
    private static function recursiveKSort(array|string|null &$array): void
    {
        if (is_array($array)) {
            ksort($array);

            foreach ($array as &$value) {
                if (is_array($value) || is_string($value)) {
                    self::recursiveKSort($value);
                }
            }
        }
    }

    /**
     * contruct a cache key by request info.
     *
     * @param  array<string>  $user_tags
     */
    private function getKeyFromRequest(Request $request, array $user_tags): string
    {
        $path = $request->getPathInfo();
        $params = $request->query();

        if ($user_tags !== []) {
            self::recursiveKSort($params);
        }

        return base64_encode($path . ($user_tags !== [] ? implode('_', $user_tags) . '_' : '') . serialize($params));
    }

    private function getTableName(string|Model $entity): string
    {
        return is_string($entity) ? $entity : $entity->getTable();
    }

    /**
     * compose key parts by user and groups.
     *
     * @return array<string>
     */
    private function getKeyPartsFromUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $tags = ['U' . $user->id];
        $role_tags = $user->roles()->get()->map(static function (Model $role): string {
            $key = $role->getKey();

            return 'R' . (is_scalar($key) ? (string) $key : '0');
        })->all();

        foreach (['groups', 'user_groups', 'user_roles'] as $relation_name) {
            if (! method_exists($user, $relation_name) || ! $user->relationLoaded($relation_name)) {
                continue;
            }

            $related = $user->getRelation($relation_name);

            if (! $related instanceof \Illuminate\Support\Collection) {
                continue;
            }

            foreach ($related as $related_model) {
                if (! $related_model instanceof Model) {
                    continue;
                }

                $key = $related_model->getKey();
                $role_tags[] = 'R' . (is_scalar($key) ? (string) $key : '0');
            }
        }

        sort($role_tags);
        array_push($tags, ...$role_tags);

        return array_map(strval(...), $tags);
    }

    private function getThreshold(): ?int
    {
        $value = config('cache.threshold');

        return is_numeric($value) ? (int) $value : null;
    }

    private function resolveDefaultCacheSeconds(): int
    {
        $durations = config('cache.duration');

        if (is_int($durations)) {
            return max(1, $durations);
        }

        if (! is_array($durations)) {
            return 300;
        }

        return $this->resolveDurationConfigToSeconds($durations);
    }

    /**
     * @param  array<int|string, mixed>  $durations
     */
    private function resolveDurationConfigToSeconds(array $durations): int
    {
        foreach (['medium', 'short', 'long'] as $key) {
            if (isset($durations[$key]) && is_numeric($durations[$key])) {
                return max(1, (int) $durations[$key]);
            }
        }

        foreach ($durations as $value) {
            if (is_numeric($value)) {
                return max(1, (int) $value);
            }
        }

        return 300;
    }
}
