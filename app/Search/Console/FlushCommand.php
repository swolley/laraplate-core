<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Laravel\Scout\Console\FlushCommand as BaseFlushCommand;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;
use Override;

final class FlushCommand extends BaseFlushCommand
{
    use HasBenchmark;
    use SearchableCommandUtils;

    protected $description = 'Flush all of the model\'s records from the index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle()
    {
        if (in_array($this->getModelClass(), ['', '0'], true) || $this->getModelClass() === false) {
            return self::INVALID;
        }

        parent::handle();

        return self::SUCCESS;
    }
}
