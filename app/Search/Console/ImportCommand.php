<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Scout\Console\ImportCommand as BaseImportCommand;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;
use Symfony\Component\Console\Command\Command;

final class ImportCommand extends BaseImportCommand
{
    use HasBenchmark;
    use SearchableCommandUtils;

    protected $description = 'Import the given model into the search index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle(Dispatcher $events)
    {
        if (in_array($this->getModelClass(), ['', '0'], true) || $this->getModelClass() === false) {
            return Command::INVALID;
        }

        parent::handle($events);

        return Command::SUCCESS;
    }
}
