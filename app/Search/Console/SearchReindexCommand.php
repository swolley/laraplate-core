<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Core\Cache\HasCache;
use Modules\Core\Overrides\Command;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\Searchable;

final class SearchReindexCommand extends Command
{
    protected $signature = 'scout:reindex {model : The model to reindex}';

    protected $description = 'Reindex documents in Search Engine <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    public function handle(): int
    {
        try {
            $model = $this->argument('model');

            if (! class_exists($model)) {
                $this->error(sprintf('Model class %s does not exist.', $model));

                return 1;
            }

            if (! in_array(Searchable::class, class_uses_recursive($model), true)) {
                $this->error(sprintf('Model class %s does not use the Searchable trait.', $model));

                return 1;
            }

            dispatch(new ReindexSearchJob($model));
            $this->info('Reindexing has been queued for model ' . $model);

            // Se il modello usa il trait HasCache, invalida la cache
            if (in_array(HasCache::class, class_uses_recursive($model), true)) {
                new $model()->invalidateCache();
                $this->info('Cache has been invalidated for model ' . $model);
            }

            return 0;
        } catch (Exception $exception) {
            Log::error('Error in model:reindex command', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->error('An error occurred: ' . $exception->getMessage());

            return 1;
        }
    }
}
