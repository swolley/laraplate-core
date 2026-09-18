<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Overrides\Command;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;
use Symfony\Component\Console\Command\Command as BaseCommand;

final class SyncMappingCommand extends Command
{
    use SearchableCommandUtils;

    #[Override]
    protected $signature = 'scout:sync-mapping {model? : The model whose index mapping to sync}';

    #[Override]
    protected $description = 'Add new (e.g. per-locale) fields to an existing search index mapping without recreating it <fg=green>(⚡ Modules\Core)</fg=green>';

    public function handle(): int
    {
        try {
            $model = $this->getModelClass();

            if ($model) {
                $modeles = [$model];
            } else {
                // Filtered by the contract rather than by the trait that satisfies it: a
                // model that declares ISearchableModel is one this command can sync, and
                // saying so lets the instance below keep its type.
                $modeles = array_filter(models(), static fn (string $model): bool => is_a($model, ISearchableModel::class, true));
            }

            $failed = [];

            foreach ($modeles as $model) {
                $model_instance = new $model();

                if (! $model_instance instanceof ISearchableModel) {
                    continue;
                }

                $engine = $model_instance->searchableUsing();

                // Additive mapping sync lives on the engine (e.g. ElasticsearchEngine::syncMapping);
                // engines without it (e.g. the database engine) are a no-op. method_exists
                // rather than is_callable: both are true here, and only one tells the
                // analyser that $engine is an object carrying that method.
                if (! method_exists($engine, 'syncMapping')) {
                    continue;
                }

                $this->info('Syncing mapping for ' . $model);

                if ((bool) $engine->syncMapping($model_instance)) {
                    $this->info('Mapping synced for ' . $model);
                } else {
                    $failed[] = $model;
                    $this->warn('Could not sync mapping for ' . $model . '. A changed field type cannot be patched; recreate the index (scout:delete-index then scout:index).');
                }
            }

            if ($failed !== []) {
                $this->error('Mapping sync failed for: ' . implode(', ', $failed));

                return BaseCommand::FAILURE;
            }

            $this->info('All index mappings are up to date.');

            return BaseCommand::SUCCESS;
        } catch (Exception $exception) {
            Log::error('Error in scout:sync-mapping command', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->error('An error occurred: ' . $exception->getMessage());

            return BaseCommand::FAILURE;
        }
    }
}
