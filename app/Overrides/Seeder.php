<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Seeder as BaseSeeder;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Helpers\HasSeedersUtils;

class Seeder extends BaseSeeder
{
    use HasBenchmark;
    use HasSeedersUtils;

    public function __construct(protected DatabaseManager $db)
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
