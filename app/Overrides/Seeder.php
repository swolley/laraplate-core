<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use function is_laraplate_owned_module;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Seeder as BaseSeeder;
use Modules\Core\Console\Concerns\HasBenchmark;
use Modules\Core\Database\Seeders\Concerns\HasSeedersUtils;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Models\Setting;
use Modules\Core\Seeding\SeedDefinition;

class Seeder extends BaseSeeder
{
    use HasBenchmark;
    use HasSeedersUtils;

    /**
     * Environment variable set by {@see BatchSeeder::bootstrapChildProcess}
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
     * Reconcile definition for the settings a module ships.
     *
     * `is_internal` follows the declaring module's ownership, not the fact that a
     * seeder wrote the row: {@see is_laraplate_owned_module()} reads `module.json`
     * `laraplate_owned`, falling back to a `swolley/laraplate-*` composer name. A
     * third-party module shipping its own settings through this definition gets
     * `is_internal = false`. The flag is structural, so a re-seed realigns rows
     * written before it existed and follows a module that changes ownership.
     * `group_name` is written on insert only: operators regroup settings freely and
     * a re-seed keeps their choice.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    protected static function internalSettingsDefinition(string $module, array $rows): SeedDefinition
    {
        $is_internal = is_laraplate_owned_module($module);

        return SeedDefinition::for(Setting::class)
            ->identity(['name'])
            ->structural(['type', 'description', 'choices', 'is_internal'])
            ->initial(['value'])
            ->ownedBy($module)
            ->rows(array_map(
                static fn (array $row): array => [...$row, 'is_internal' => $is_internal],
                $rows,
            ));
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
