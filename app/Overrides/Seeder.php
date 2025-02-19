<?php

namespace Modules\Core\Overrides;

use Modules\Core\Helpers\HasBenchmark;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Helpers\HasSeedersUtils;
use Illuminate\Database\Seeder as BaseSeeder;

class Seeder extends BaseSeeder
{
    use HasSeedersUtils, HasBenchmark;

    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
        if (config('app.debug')) {
            $this->startBenchmark();
        }
    }

    public function __destruct()
    {
        if (config('app.debug')) {
            $this->endBenchmark();
        }
    }
}
