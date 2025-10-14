<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\Widget;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Searchable;
use Modules\Core\Helpers\HasChildren;

final class SearchEngineHealthTableWidget extends Widget
{
    protected string $view = 'core::filament.widgets.search-engine-health';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $data = [
            'engine' => null,
            'health' => null,
            'models' => [],
            'error' => null,
        ];

        try {
            $engine = app(EngineManager::class)->engine();
            $data['engine'] = $engine;

            // Try to get health information
            if (method_exists($engine, 'health')) {
                $data['health'] = $engine->health();
            }

            // Get searchable models and their counts
            $models = models(filter: fn ($model): bool => class_uses_trait($model, Searchable::class) && ! class_uses_trait($model, HasChildren::class, false));
            $modelData = [];

            foreach ($models as $model) {
                $instance = new $model();

                $modelData[] = [
                    'name' => $model,
                    'searchable_as' => $instance->searchableAs(),
                    'count' => $model::query()->count(),
                    'index_exists' => $engine->checkIndex($instance),
                    'documents' => $engine->stats()[$instance->searchableAs()]['num_documents'] ?? 0,
                ];
            }

            $data['models'] = $modelData;
        } catch (Exception $e) {
            $data['error'] = $e->getMessage();
        }

        return $data;
    }
}
