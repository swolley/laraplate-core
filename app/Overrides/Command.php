<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Console\Command as BaseCommand;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Helpers\HasBenchmark;

class Command extends BaseCommand
{
    use HasBenchmark;

    protected DatabaseManager $db;

    public function __construct(?DatabaseManager $db = null)
    {
        parent::__construct();

        if ($db instanceof DatabaseManager) {
            $this->db = $db;
        }

        if (!app()->bound('runningUnitTests') || !app()->runningUnitTests()) {
            $this->startBenchmark();
        }
    }

    public function __destruct()
    {
        if ((!app()->bound('runningUnitTests') || !app()->runningUnitTests()) && app()->bound('config') && $this->benchmarkStartTime !== null) {
            $this->endBenchmark();
        }
    }
}
