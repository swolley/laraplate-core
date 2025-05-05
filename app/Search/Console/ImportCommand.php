<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Override;
use Modules\Core\Helpers\HasBenchmark;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Command\Command;
use Modules\Core\Search\Traits\SearchableCommandUtils;

final class ImportCommand extends \Laravel\Scout\Console\ImportCommand
{
    use HasBenchmark, SearchableCommandUtils;

    protected $description = 'Import the given model into the search index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle(Dispatcher $events)
    {
        if (in_array($this->getModelClass(), ['', '0'], true) || $this->getModelClass() === false) {
            return Command::FAILURE;
        }

        return parent::handle($events);
    }
}
