<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Modules\Core\Overrides\Command;
use Modules\Core\Cache\HasCache;
use Modules\Core\Cache\Searchable;
use Illuminate\Support\Facades\Log;
use Modules\Core\Jobs\ReindexElasticsearchJob;

class ElasticsearchReindexCommand extends Command
{
    protected $signature = 'model:reindex {model : The model to reindex}';

    protected $description = 'Reindex documents in Elasticsearch <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        try {
            $model_class = $this->argument('model');

            if (!class_exists($model_class)) {
                $this->error("Model class {$model_class} does not exist.");
                return 1;
            }

            if (!in_array(Searchable::class, class_uses_recursive($model_class))) {
                $this->error("Model class {$model_class} does not use the Searchable trait.");
                return 1;
            }

            ReindexElasticsearchJob::dispatch($model_class);
            $this->info('Reindexing has been queued for model ' . $model_class);

            // Se il modello usa il trait HasCache, invalida la cache
            if (in_array(HasCache::class, class_uses_recursive($model_class))) {
                (new $model_class)->invalidateCache();
                $this->info('Cache has been invalidated for model ' . $model_class);
            }

            return 0;
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
