<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

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
     * Reconcile definition for the settings a first-party module ships.
     *
     * Every row is stamped `is_internal = true`, and the flag is structural so a
     * re-seed realigns rows written before it existed. `group_name` is written on
     * insert only: operators regroup settings freely and a re-seed keeps their choice.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    protected static function internalSettingsDefinition(string $module, array $rows): SeedDefinition
    {
        return SeedDefinition::for(Setting::class)
            ->identity(['name'])
            ->structural(['type', 'description', 'choices', 'is_internal'])
            ->initial(['value'])
            ->ownedBy($module)
            ->rows(array_map(
                static fn (array $row): array => [...$row, 'is_internal' => true],
                $rows,
            ));
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
