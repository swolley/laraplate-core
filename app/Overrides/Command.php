<?php

namespace Modules\Core\Overrides;

use Illuminate\Console\Command as BaseCommand;
use Modules\Core\Helpers\HasBenchmark;

class Command extends BaseCommand
{
    use HasBenchmark;

    public function handle()
    {
        if (config('app.debug')) {
            $this->startBenchmark();
        }

        $result = parent::handle();

        if (config('app.debug')) {
            $this->endBenchmark();
        }

        return $result;
    }
}