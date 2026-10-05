<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Search\Contracts\IReportsVectorDimensions;
use Modules\Core\Search\Contracts\IVectorSearchAvailability;
use Modules\Core\Search\DTOs\VectorAvailability;

/**
 * Default guard: vector search is available unless it is disabled, suspended by an embedding model
 * switch, or the engine's index holds vectors of a dimension other than the configured one.
 */
final class VectorSearchAvailability implements IVectorSearchAvailability
{
    private const string CACHE_PREFIX = 'core.search.vector.dimensions_check.';

    private const string KEYS_KEY = 'core.search.vector.dimensions_check_keys';

    private const int TTL_SECONDS = 60;

    public function check(Model $model): VectorAvailability
    {
        if (! (bool) config('core.search.vector.enabled')) {
            return VectorAvailability::no('disabled');
        }

        $suspended_reason = config('core.search.vector.suspended_reason');

        if (is_string($suspended_reason) && $suspended_reason !== '') {
            return VectorAvailability::no('suspended');
        }

        $indexed = $this->indexedDimensions($model);

        if ($indexed !== null && $indexed !== (int) config('core.search.vector.dimensions')) {
            return VectorAvailability::no('dimension_mismatch');
        }

        return VectorAvailability::yes();
    }

    /**
     * Drops the cached dimension checks, so the next check asks the engine again.
     */
    public function forget(): void
    {
        $keys = Cache::get(self::KEYS_KEY, []);

        foreach (is_array($keys) ? $keys : [] as $key) {
            Cache::forget((string) $key);
        }

        Cache::forget(self::KEYS_KEY);
    }

    private function indexedDimensions(Model $model): ?int
    {
        $engine = method_exists($model, 'searchableUsing') ? $model->searchableUsing() : null;

        if (! $engine instanceof IReportsVectorDimensions) {
            return null;
        }

        $key = self::CACHE_PREFIX . $model::class;
        $cached = Cache::get($key);

        if (is_array($cached) && array_key_exists('dimensions', $cached)) {
            return $cached['dimensions'];
        }

        $dimensions = $engine->indexedVectorDimensions($model);
        Cache::put($key, ['dimensions' => $dimensions], self::TTL_SECONDS);

        $keys = Cache::get(self::KEYS_KEY, []);
        $keys = is_array($keys) ? $keys : [];

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put(self::KEYS_KEY, $keys, self::TTL_SECONDS * 60);
        }

        return $dimensions;
    }
}
