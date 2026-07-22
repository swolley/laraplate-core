<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Seeder as BaseSeeder;
use Modules\Core\Console\Concerns\HasBenchmark;
use Modules\Core\Database\Seeders\Concerns\HasSeedersUtils;
use Modules\Core\Models\Setting;

class Seeder extends BaseSeeder
{
    use HasBenchmark;
    use HasSeedersUtils;

    /**
     * Environment variable set by {@see \Modules\Core\Helpers\BatchSeeder::bootstrapChildProcess}
     * in fork workers so destructors skip benchmark output (shared STDOUT with parent).
     */
    public const string PARALLEL_BATCH_WORKER_ENV = 'LARAPLE_PARALLEL_BATCH_WORKER';

    protected bool $disableBenchmark = false;

    public function __construct(protected DatabaseManager $db)
    {
        $this->db = $db;

        if (config('app.debug') && ! $this->disableBenchmark) {
            $this->startBenchmark();
        }
    }

    public function __destruct()
    {
        if ($this->isParallelBatchForkWorker()) {
            return;
        }

        if (config('app.debug') && ! $this->disableBenchmark) {
            $this->endBenchmark();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     */
    protected function seedSettingDefinitions(array $definitions): void
    {
        if ($definitions === []) {
            return;
        }

        $existing = Setting::query()
            ->withoutGlobalScopes()
            ->whereIn('name', array_column($definitions, 'name'))
            ->select(['name'])
            ->pluck('name')
            ->flip()
            ->all();

        $newDefinitions = array_filter(
            $definitions,
            static fn (array $definition): bool => ! isset($existing[$definition['name']]),
        );

        if ($newDefinitions === []) {
            $this->command?->line('    - runtime settings already exist');

            return;
        }

        (new Setting)->getConnection()->transaction(function () use ($newDefinitions): void {
            foreach ($newDefinitions as $definition) {
                Setting::factory()->persistedWithoutApprovalCapture()->create($definition);
                $this->command?->line("    - {$definition['name']} <fg=green>created</>");
            }
        });
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
