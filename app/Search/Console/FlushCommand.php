<?php

namespace Modules\Core\Search\Console;

use Modules\Core\Search\Traits\SearchableCommandUtils;
use Modules\Core\Helpers\HasBenchmark;

class FlushCommand extends \Laravel\Scout\Console\FlushCommand
{
    use SearchableCommandUtils, HasBenchmark;

    protected $description = 'Flush all of the model\'s records from the index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[\Override]
    public function handle()
    {
        if (!$this->getModelClass()) {
            return self::FAILURE;
        }
        return parent::handle();
    }
}
