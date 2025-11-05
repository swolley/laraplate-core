<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-type HasCacheType HasCache
 */
trait HasCache
{
    public function getCacheKey(): string
    {
        return property_exists($this, 'cacheKey') ? $this->cacheKey : $this->getTable();
    }

    public function usesCache(): bool
    {
        return true;
    }

    public function invalidateCache(): void
    {
        if (Cache::supportsTags()) {
            Cache::tags(Cache::getCacheTags($this->getTable()))->flush();
        } else {
            Cache::forget($this->getCacheKey());
        }
    }

    protected static function bootHasCache(): void
    {
        static::saved(function (Model $model): void {
            $model->invalidateCache();
        });

        static::deleted(function (Model $model): void {
            $model->invalidateCache();
        });
    }
}
