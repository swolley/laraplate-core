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
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class CheckIndexCommand extends Command
{
    use SearchableCommandUtils;

    #[Override]
    protected $signature = 'scout:check-index {model? : The model to check}';

    #[Override]
    protected $description = 'Check indexes in Search Engine <fg=green>(⚡ Modules\Core)</fg=green>';

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

            $missing_indexes = [];
            $structure_mismatches = [];

            foreach ($modeles as $model) {
                $this->info('Checking model ' . $model);
                $model_instance = new $model();
                $engine = $model_instance->searchableUsing();

                // Index-existence verification lives on the engine (e.g. ElasticsearchEngine::checkIndex);
                // engines without it (e.g. the database engine) are treated as always valid.
                $index_ok = ! is_callable([$engine, 'checkIndex']) || (bool) $engine->checkIndex($model_instance);

                if (! $index_ok) {
                    $missing_indexes[] = $model;
                    $this->warn('Model ' . $model . ' has a wrong or missing index.');

                    continue;
                }

                // Existence is not enough: a field whose type drifted (e.g. text -> object)
                // keeps the index present but breaks every write. Engines exposing
                // checkIndexStructure validate the live mapping against the model schema.
                $structure_ok = ! is_callable([$engine, 'checkIndexStructure']) || (bool) $engine->checkIndexStructure($model_instance);

                if (! $structure_ok) {
                    $structure_mismatches[] = $model;
                    $this->warn('Model ' . $model . ' has a stale index structure: the live mapping no longer matches the model schema. Recreate the index (scout:delete-index then scout:index) — reindexing alone will not fix a changed field type.');
                }
            }

            if ($missing_indexes === [] && $structure_mismatches === []) {
                $this->info('All models have the correct indexes.');

                return BaseCommand::SUCCESS;
            }

            if ($missing_indexes !== [] && confirm('Do you want to reindex the models with a missing index?')) {
                Bus::chain(
                    collect($missing_indexes)->map(static fn (string $model): object => new ReindexSearchJob($model)),
                )->dispatch();
                $this->info('Reindexing has been queued for the models with a missing index.');
            }

            if ($structure_mismatches !== []) {
                $this->error('Stale index structure (recreate with scout:delete-index then scout:index): ' . implode(', ', $structure_mismatches));

                return BaseCommand::FAILURE;
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
