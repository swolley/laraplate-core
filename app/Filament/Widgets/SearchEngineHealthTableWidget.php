<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\Widget;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Searchable;

final class SearchEngineHealthTableWidget extends Widget
{
    protected string $view = 'core::filament.widgets.search-engine-health';

    protected int|string|array $columnSpan = 'full';

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
