<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\Concerns\HasValidations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Setting;
use Modules\Core\Overrides\Command;
use Override;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Throwable;

/**
 * Artisan command to pre-warm critical runtime cache entries.
 *
 * Warms the following entries:
 * 1. All Setting groups (all records)
 * 2. Cron jobs
 * 3. Version strategies (all Setting records with group_name = 'versioning')
 * 4. Permission existence map (all Permission names → true)
 */
final class WarmCacheCommand extends Command
{
    #[Override]
    protected $signature = 'cache:warm';

    #[Override]
    protected $description = 'Pre-warm critical runtime cache entries (settings, cron jobs, version strategies, permissions) <fg=green>(⚡ Modules\Core)</fg=green>';

    public function handle(): int
    {
        if ($this->output === null) {
            $this->setOutput(new OutputStyle(new StringInput(''), new NullOutput()));
        }

        $start = microtime(true);
        $total_warmed = 0;
        $failed_steps = 0;
        $total_steps = 4;

        $this->info('Warming cache entries...');

        // Step 1: Warm all Settings (all groups)
        try {
            $warmed = $this->warmSettings();
            $total_warmed += $warmed;
            $this->line("  <fg=green>✓</fg=green> Settings: {$warmed} entries warmed.");
        } catch (Throwable $e) {
            $failed_steps++;
            Log::error('cache:warm — failed to warm settings', ['exception' => $e->getMessage()]);
            $this->line('  <fg=red>✗</fg=red> Settings: failed (' . $e->getMessage() . ')');
        }

        // Step 2: Warm cron jobs
        try {
            $warmed = $this->warmCronJobs();
            $total_warmed += $warmed;
            $this->line("  <fg=green>✓</fg=green> Cron jobs: {$warmed} entries warmed.");
        } catch (Throwable $e) {
            $failed_steps++;
            Log::error('cache:warm — failed to warm cron jobs', ['exception' => $e->getMessage()]);
            $this->line('  <fg=red>✗</fg=red> Cron jobs: failed (' . $e->getMessage() . ')');
        }

        // Step 3: Warm version strategies
        try {
            $warmed = $this->warmVersionStrategies();
            $total_warmed += $warmed;
            $this->line("  <fg=green>✓</fg=green> Version strategies: {$warmed} entries warmed.");
        } catch (Throwable $e) {
            $failed_steps++;
            Log::error('cache:warm — failed to warm version strategies', ['exception' => $e->getMessage()]);
            $this->line('  <fg=red>✗</fg=red> Version strategies: failed (' . $e->getMessage() . ')');
        }

        // Step 4: Warm permission existence map
        try {
            $warmed = $this->warmPermissionExistenceMap();
            $total_warmed += $warmed;
            $this->line("  <fg=green>✓</fg=green> Permission existence map: {$warmed} entries warmed.");
        } catch (Throwable $e) {
            $failed_steps++;
            Log::error('cache:warm — failed to warm permission existence map', ['exception' => $e->getMessage()]);
            $this->line('  <fg=red>✗</fg=red> Permission existence map: failed (' . $e->getMessage() . ')');
        }

        $elapsed = round((microtime(true) - $start) * 1000, 2);

        $this->newLine();
        $this->info("Cache warming complete: {$total_warmed} entries populated in {$elapsed}ms.");

        if ($failed_steps === $total_steps) {
            $this->error('All warming steps failed.');

            return BaseCommand::FAILURE;
        }

        return BaseCommand::SUCCESS;
    }

    /**
     * Warm all Setting records into the persistent cache.
     * Caches the full collection under the settings table key (used by HasCache).
     * Returns the number of cache entries written.
     */
    private function warmSettings(): int
    {
        if (! SchemaInspector::getInstance()->hasTable(CoreTables::Settings->value)) {
            return 0;
        }

        $settings = Setting::query()->get();

        // Cache under the model's own cache key (table name), matching HasCache behaviour
        Cache::forever(CacheManager::key((new Setting())->getTable()), $settings);

        return 1;
    }

    /**
     * Warm cron jobs into the persistent cache.
     * Mirrors the logic in CoreServiceProvider::registerCommandSchedules().
     * Returns the number of cache entries written.
     */
    private function warmCronJobs(): int
    {
        if (! SchemaInspector::getInstance()->hasTable(CoreTables::CronJobs->value)) {
            return 0;
        }

        $cache_key = new CronJob()->getTable();
        $crons = CronJob::query()->active()->select(['command', 'schedule'])->get()->toArray();

        /** @var \Illuminate\Cache\Repository $cache */
        $cache = Cache::store();

        if ($cache->supportsTags() && method_exists($cache, 'getCacheTags')) {
            $cache_tags = $cache->getCacheTags();
            Cache::tags($cache_tags)->put($cache_key, $crons);
        } else {
            Cache::put($cache_key, $crons);
        }

        return 1;
    }

    /**
     * Warm version strategies into the persistent cache.
     * Loads all Setting records with group_name = 'versioning' and stores them
     * under the prefixed key used by HasVersions::getVersionStrategy().
     * Returns the number of cache entries written.
     */
    private function warmVersionStrategies(): int
    {
        if (! SchemaInspector::getInstance()->hasTable(CoreTables::Settings->value)) {
            return 0;
        }

        $versioning_settings = Setting::query()->where('group_name', '=', 'versioning')->get(); // @phpstan-ignore argument.type

        Cache::forever(CacheManager::key('version_strategies'), $versioning_settings);

        // Reset L1 so subsequent calls re-read from the freshly warmed L2
        HasVersions::resetVersionStrategyCache();

        return 1;
    }

    /**
     * Warm the permission existence map into the static in-memory cache.
     * Loads all Permission names and marks them as existing (true).
     * Returns the number of permission entries warmed.
     */
    private function warmPermissionExistenceMap(): int
    {
        if (! SchemaInspector::getInstance()->hasTable(CoreTables::Permissions->value)) {
            return 0;
        }

        // Reset first to ensure idempotency
        HasValidations::resetPermissionExistenceCache();

        $permission_names = Permission::query()->pluck('name');

        // Populate the static cache by calling the internal mechanism via reflection
        // We use the public reset + direct static property access pattern established
        // by the HasValidations trait design.
        $reflection = new ReflectionProperty(HasValidations::class, 'permission_existence_cache');

        /** @var array<string, bool> $cache_map */
        $cache_map = [];

        foreach ($permission_names as $name) {
            $cache_map[(string) $name] = true; // @phpstan-ignore cast.string
        }

        $reflection->setValue(null, $cache_map);

        return $permission_names->count();
    }
}
