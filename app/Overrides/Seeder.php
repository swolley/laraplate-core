<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Seeder as BaseSeeder;
use Modules\Core\Helpers\HasBenchmark;
use Modules\Core\Helpers\HasSeedersUtils;

class Seeder extends BaseSeeder
{
    private $disableBenchmark;
    use HasBenchmark;
    use HasSeedersUtils;

    /**
     * Environment variable set by {@see \Modules\Core\Helpers\BatchSeeder::bootstrapChildProcess}
     * in fork workers so destructors skip benchmark output (shared STDOUT with parent).
     */
    public const string PARALLEL_BATCH_WORKER_ENV = 'LARAPLE_PARALLEL_BATCH_WORKER';

    public function __construct(protected DatabaseManager $db)
    {
        $this->db = $db;

        if (config('app.debug') && ! (property_exists($this, 'disableBenchmark') && $this->disableBenchmark === true)) {
            $this->startBenchmark();
        }
    }

    public function __destruct()
    {
        if ($this->isParallelBatchForkWorker()) {
            return;
        }

        if (config('app.debug')) {
            $this->endBenchmark();
        }
    }

    /**
     * Whether this PHP process is a spatie/fork worker started by BatchSeeder.
     */
    private function isParallelBatchForkWorker(): bool
    {
        $flag = getenv(self::PARALLEL_BATCH_WORKER_ENV);

        return $flag === '1' || $flag === 'true';
    }
}
