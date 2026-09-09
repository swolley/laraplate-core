<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Console\DeleteIndexCommand as BaseDeleteIndexCommand;
use Laravel\Scout\EngineManager;
use Modules\Core\Console\Concerns\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;
use Symfony\Component\Console\Command\Command;

final class DeleteIndexCommand extends BaseDeleteIndexCommand
{
    use HasBenchmark;
    use SearchableCommandUtils;

    #[Override]
    protected $signature = 'scout:delete-index {model : The model to delete the index for}';

    #[Override]
    protected $description = 'Delete an index for a model <fg=green>(⚡ Modules\Core)</fg=green>';

    #[Override]
    public function handle(EngineManager $manager): int
    {
        try {
            $model = $this->getModelClass();

            if (\in_array($model, ['', '0', false], true)) {
                return Command::INVALID;
            }

            $this->addArgument('name');
            $this->input->setArgument('name', new $model()->indexableAs());

            parent::handle($manager);

            return Command::SUCCESS;
        } catch (Exception $exception) {
            Log::error('Error in scout:delete-index command', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->error('An error occurred: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
