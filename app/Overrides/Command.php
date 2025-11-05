<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Console\Command as BaseCommand;
use Modules\Core\Helpers\HasBenchmark;

class Command extends BaseCommand
{
    use HasBenchmark;

    public function __construct()
    {
        parent::__construct();

        if (! app()->bound('runningUnitTests') || ! app()->runningUnitTests()) {
            $this->startBenchmark();
        }
    }

    public function __destruct()
    {
        if ((! app()->bound('runningUnitTests') || ! app()->runningUnitTests()) && app()->bound('config') && $this->benchmarkStartTime !== null) {
            $this->endBenchmark();
        }
    }
}
