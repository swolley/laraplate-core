<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Console\ImportCommand as BaseImportCommand;
use Modules\Core\Console\Concerns\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;
use Symfony\Component\Console\Command\Command;

final class ImportCommand extends BaseImportCommand
{
    use HasBenchmark;
    use SearchableCommandUtils;

    #[Override]
    protected $signature = 'scout:import
            {model : Class name of model to bulk import}
            {--fresh : Flush the index before importing}
            {--c|chunk= : The number of records to import at a time (Defaults to configuration value: `scout.chunk.searchable`)}';


    #[Override]
    protected $description = 'Import the given model into the search index <fg=green>(⚡ Modules\Core)</fg=green>';

    #[Override]
    public function handle(Dispatcher $events): int
    {
        try {
            $model = $this->getModelClass();

            if (\in_array($model, ['', '0', false], true)) {
                return Command::INVALID;
            }

            parent::handle($events);

            return Command::SUCCESS;
        } catch (Exception $exception) {
            Log::error('Error in scout:import command', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->error('An error occurred: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
