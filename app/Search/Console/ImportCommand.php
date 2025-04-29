<?php

namespace Modules\Core\Search\Console;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Modules\Core\Helpers\HasBenchmark;

class ImportCommand extends \Laravel\Scout\Console\ImportCommand
{
    use SearchableCommandUtils, HasBenchmark;

    protected $description = 'Import the given model into the search index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[\Override]
    public function handle(Dispatcher $events)
    {
        if (!$this->getModelClass()) return static::FAILURE;
        return parent::handle($events);
    }
}
