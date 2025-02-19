<?php

namespace Modules\Core\Overrides;

use Illuminate\Console\Command as BaseCommand;
use Modules\Core\Helpers\HasBenchmark;
use Illuminate\Database\DatabaseManager;

class Command extends BaseCommand
{
    use HasBenchmark;

    protected DatabaseManager $db;

    public function __construct(?DatabaseManager $db = null)
    {
        parent::__construct();

        // if (self::class !== $this::class) {
        if ($db) {
            $this->db = $db;
        }
        if (config('app.debug')) {
            $this->startBenchmark();
        }
        // }
    }

    public function __destruct()
    {
        // if (self::class !== static::class) {
        if (config('app.debug') && isset($this->benchmarkStartTime)) {
            $this->endBenchmark();
        }
        // }
    }
}
