<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Searchable;
use Modules\Core\Filament\Pages\CacheHealth;
use Override;

final class SearchEngineHealthTableWidget extends Widget
{
    /**
     * Cache TTL in seconds. Shown in widget heading so users know data is refreshed about every this period.
     */
    private const int CACHE_TTL_SECONDS = 300;

    #[Override]
    protected string $view = 'core::filament.widgets.search-engine-health';

    #[Override]
    protected int|string|array $columnSpan = 'full';

    #[Override]
    protected static ?int $sort = 51;

    public static function canView(): bool
    {
        // Only show on CacheHealth page, hide from dashboard
        $url = request()->url();
        $cacheHealthUrl = CacheHealth::getUrl();

        if (Str::contains($url, $cacheHealthUrl)) {
            return true;
        }

        return Str::contains($url, '/health/cache');
    }

    protected function getViewData(): array
    {
        $cache_key = 'filament_search_engine_health_widget';
        $cache_ttl = self::CACHE_TTL_SECONDS;
        $cached = Cache::remember($cache_key, $cache_ttl, fn (): array => $this->fetchSearchEngineData());

        $cached['cache_minutes'] = (int) ceil($cache_ttl / 60);

        return $cached;
    }

    /**
     * @return array{driver: string|null, models: array<int, array>, error: string|null}
     */
    private function fetchSearchEngineData(): array
    {
        $data = [
            'driver' => null,
            'models' => [],
            'error' => null,
        ];

        try {
            $engine = resolve(EngineManager::class)->engine();
            $data['driver'] = config('scout.driver', 'unknown');

            $models = models(filter: static fn (string|object $model): bool => class_uses_trait($model, Searchable::class));
            $modelData = [];

            foreach ($models as $model) {
                $instance = new $model();

                try {
                    $stats = $engine->stats();
                    $documents = $stats[$instance->searchableAs()]['num_documents'] ?? 0;
                } catch (Exception) {
                    $documents = 0;
                }

                $modelData[] = [
                    'name' => class_basename($model),
                    'full_name' => $model,
                    'searchable_as' => $instance->searchableAs(),
                    'count' => $model::query()->count(),
                    'index_exists' => $engine->checkIndex($instance),
                    'documents' => $documents,
                ];
            }

            $data['models'] = $modelData;
        } catch (Exception $exception) {
            $data['error'] = $exception->getMessage();
        }

        return $data;
    }
}
