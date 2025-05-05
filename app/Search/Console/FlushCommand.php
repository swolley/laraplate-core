<?php

declare(strict_types=1);

namespace Modules\Core\Search\Console;

use Override;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Search\Traits\SearchableCommandUtils;

final class FlushCommand extends \Laravel\Scout\Console\FlushCommand
{
    use HasBenchmark, SearchableCommandUtils;

    protected $description = 'Flush all of the model\'s records from the index <fg=yellow>(⛭ Modules\Core)</fg=yellow>';

    #[Override]
    public function handle()
    {
        if (in_array($this->getModelClass(), ['', '0'], true) || $this->getModelClass() === false) {
            return self::FAILURE;
        }

        return parent::handle();
    }
}
