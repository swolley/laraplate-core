<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Cache\HasCache;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Log;
use Modules\Core\Jobs\ReindexElasticsearchJob;

use function Laravel\Prompts\confirm;

class ElasticsearchCheckCommand extends Command
{
    protected $signature = 'model:check {model? : The model to check}';

    protected $description = 'Check indexes in Elasticsearch <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        try {
            $model_class = $this->argument('model');

            if ($model_class) {
                if (!class_exists($model_class)) {
                    $this->error("Model class {$model_class} does not exist.");
                    return 1;
                }

                if (!in_array(Searchable::class, class_uses_recursive($model_class))) {
                    $this->error("Model class {$model_class} does not use the Searchable trait.");
                    return 1;
                }

                $model_classes = [$model_class];
            } else {
                $model_classes = array_filter(models(), fn($model) => in_array(Searchable::class, class_uses_recursive($model)));
            }

            $wrong_or_missing_indexes = [];

            foreach ($model_classes as $model_class) {
                $this->info('Checking model ' . $model_class);
                $model = new $model_class;
                if (!$model->checkIndex()) {
                    $wrong_or_missing_indexes[] = $model_class;
                    $this->warn('Model ' . $model_class . ' has a wrong or missing index.');
                }
            }

            if (empty($wrong_or_missing_indexes)) {
                $this->info('All models have the correct indexes.');
                return static::SUCCESS;
            }

            if (confirm('Do you want to reindex the unmathced models?')) {
                foreach ($wrong_or_missing_indexes as $model_class) {
                    ReindexElasticsearchJob::dispatch($model_class);
                    // Se il modello usa il trait HasCache, invalida la cache
                    if (in_array(HasCache::class, class_uses_recursive($model_class))) {
                        (new $model_class)->invalidateCache();
                        $this->info('Cache has been invalidated for model ' . $model_class);
                    }
                }
            }

            return static::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error in elasticsearch:reindex command', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
    }
}
