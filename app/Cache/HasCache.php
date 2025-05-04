<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

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
            Cache::tags([$this->getTable()])->flush();
        } else {
            Cache::forget($this->getCacheKey());
        }
    }

    /**
     * define $cacheKey to override the default one.
     *
     * @var string
     */
    // protected static string $cacheKey;

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
