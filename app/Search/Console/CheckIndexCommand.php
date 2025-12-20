<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use function Laravel\Prompts\confirm;

use Exception;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Modules\Core\Overrides\Command;
use Modules\Core\Search\Jobs\ReindexSearchJob;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class CheckIndexCommand extends Command
{
    use SearchableCommandUtils;

    protected $signature = 'scout:check-index {model? : The model to check}';

    protected $description = 'Check indexes in Search Engine <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $model = $this->getModelClass();

            if ($model) {
                $modeles = [$model];
            } else {
                $modeles = array_filter(models(), static fn (string $model): bool => in_array(Searchable::class, class_uses_recursive($model), true));
            }

            $wrong_or_missing_indexes = [];

            foreach ($modeles as &$model) {
                $this->info('Checking model ' . $model);
                $model_instance = new $model();

                if (! $model_instance->checkIndex()) {
                    $wrong_or_missing_indexes[] = $model;
                    $this->warn('Model ' . $model . ' has a wrong or missing index.');
                }
            }

            if ($wrong_or_missing_indexes === []) {
                $this->info('All models have the correct indexes.');

                return BaseCommand::SUCCESS;
            }

            if (confirm('Do you want to reindex the unmatched models?')) {
                Bus::chain(
                    collect($wrong_or_missing_indexes)->map(static fn (string $model): object => new ReindexSearchJob($model)),
                )->dispatch();
                $this->info('Reindexing has been queued for the unmatched models.');
            }

            return BaseCommand::SUCCESS;
        } catch (Exception $exception) {
            Log::error('Error in elasticsearch:reindex command', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->error('An error occurred: ' . $exception->getMessage());

            return 1;
        }
    }
}
