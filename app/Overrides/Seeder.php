<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Modules\Core\Helpers\HasBenchmark;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Helpers\HasSeedersUtils;
use Illuminate\Database\Seeder as BaseSeeder;

final class Seeder extends BaseSeeder
{
    use HasBenchmark, HasSeedersUtils;

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
