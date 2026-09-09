<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Console\FlushCommand as BaseFlushCommand;
use Modules\Core\Console\Concerns\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;
use Symfony\Component\Console\Command\Command;

final class FlushCommand extends BaseFlushCommand
{
    use HasBenchmark;
    use SearchableCommandUtils;

    
    #[Override]
    protected $signature = 'scout:flush {model? : Class name of the model to flush}';

    #[Override]
    protected $description = 'Flush all of the model\'s records from the index <fg=green>(⚡ Modules\Core)</fg=green>';

    #[Override]
    public function handle(): int
    {
        try {
        $model = $this->getModelClass();

            if (\in_array($model, ['', '0', false], true)) {
                return Command::INVALID;
            }

            parent::handle();

            return Command::SUCCESS;
        } catch (Exception $exception) {
            Log::error('Error in scout:flush command', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
            $this->error('An error occurred: ' . $exception->getMessage());

            return Command::FAILURE;
        }
    }
}
