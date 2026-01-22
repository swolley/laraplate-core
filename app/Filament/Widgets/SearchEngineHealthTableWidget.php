<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Searchable;
use Modules\Core\Filament\Pages\CacheHealth;

final class SearchEngineHealthTableWidget extends Widget
{
    protected string $view = 'core::filament.widgets.search-engine-health';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 51;

    public static function canView(): bool
    {
        // Only show on CacheHealth page, hide from dashboard
        $url = request()->url();
        $cacheHealthUrl = CacheHealth::getUrl();

        return Str::contains($url, $cacheHealthUrl) || Str::contains($url, '/health/cache');
    }

    protected function getViewData(): array
    {
        $data = [
            'driver' => null,
            'models' => [],
            'error' => null,
        ];

        try {
            $engine = resolve(EngineManager::class)->engine();
            $data['driver'] = config('scout.driver', 'unknown');

            // Get searchable models and their counts
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
