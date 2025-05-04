<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Laravel\Scout\EngineManager;
use Modules\Core\Cache\HasCache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Overrides\Command;
use function Laravel\Prompts\confirm;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Symfony\Component\Console\Command\Command as BaseCommand;

class CheckIndexCommand extends Command
{
    use SearchableCommandUtils;

    protected $signature = 'scout:check-index {model? : The model to check}';

    protected $description = 'Check indexes in Search Engine <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     * @return int
     */
    public function handle(): int
    {
        try {
            $model = $this->getModelClass();

            if ($model) {
                $modeles = [$model];
            } else {
                $modeles = array_filter(models(), fn($model) => in_array(Searchable::class, class_uses_recursive($model)));
            }

            $wrong_or_missing_indexes = [];

            foreach ($modeles as &$model) {
                $this->info('Checking model ' . $model);
                $model = new $model();
                if (!$model->checkIndex()) {
                    $wrong_or_missing_indexes[] = $model;
                    $this->warn('Model ' . $model . ' has a wrong or missing index.');
                }
            }

            if ($wrong_or_missing_indexes === []) {
                $this->info('All models have the correct indexes.');
                return BaseCommand::SUCCESS;
            }

            if (confirm('Do you want to reindex the unmathced models?')) {
                foreach ($wrong_or_missing_indexes as &$model) {
                    ReindexSearchJob::dispatch($model);
                    // Se il modello usa il trait HasCache, invalida la cache
                    if (in_array(HasCache::class, class_uses_recursive($model))) {
                        (new $model())->invalidateCache();
                        $this->info('Cache has been invalidated for model ' . $model);
                    }
                }
            }

            return BaseCommand::SUCCESS;
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
