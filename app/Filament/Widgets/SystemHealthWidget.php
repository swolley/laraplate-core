<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Scout\EngineManager;
use Modules\Core\Search\Contracts\ISearchEngine;
use Override;
use Throwable;

final class SystemHealthWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 10;

    #[Override]
    protected ?string $heading = 'Core';

    #[Override]
    protected ?string $pollingInterval = '60s';

    public function getColumns(): array
    {
        return [
            'md' => 3,
        ];
    }

    protected function getStats(): array
    {
        $stats = [];

        // Cache status
        $cache_store = (string) config('cache.default');
        $cache_driver = ucfirst((string) config("cache.stores.{$cache_store}.driver", $cache_store));

        $cache_works = Cache::store()->ping();

        $stats[] = Stat::make('Cache', $cache_driver)
            ->description($cache_works ? 'Cache is working' : 'Cache is not working')
            ->descriptionIcon($cache_works ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color($cache_works ? 'success' : 'danger');

        // Database connections
        // try {
        //     $connections = array_keys(config('database.connections', []));
        //     $active_connections = 0;

        //     foreach ($connections as $connection) {
        //         try {
        //             DB::connection($connection)->getPdo();
        //             $active_connections++;
        //         } catch (\Exception) {
        //             // Connection failed
        //         }
        //     }

        //     $stats[] = Stat::make('Database', "{$active_connections}/" . count($connections))
        //         ->description('Active connections')
        //         ->descriptionIcon('heroicon-o-server')
        //         ->color($active_connections === count($connections) ? 'success' : 'warning');
        // } catch (\Exception) {
        //     $stats[] = Stat::make('Database', 'Error')
        //         ->description('Unable to check database status')
        //         ->descriptionIcon('heroicon-o-exclamation-triangle')
        //         ->color('gray');
        // }

        $stats[] = $this->searchStat();
        $stats[] = $this->queueStat();

        return $stats;
    }

    private function queueStat(): Stat
    {
        $queue_driver = (string) config('queue.default', 'sync');
        $queue_label = ucfirst($queue_driver);

        if ($queue_driver === 'sync') {
            return Stat::make('Queue', $queue_label)
                ->description('Queue is synchronous')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray');
        }

        if ($queue_driver !== 'redis' || ! interface_exists(MasterSupervisorRepository::class)) {
            return Stat::make('Queue', $queue_label)
                ->description('Worker status not tracked')
                ->descriptionIcon('heroicon-o-queue-list')
                ->color('gray');
        }

        try {
            $masters = resolve(MasterSupervisorRepository::class)->all();
        } catch (Throwable) {
            return Stat::make('Queue', $queue_label)
                ->description('Unable to check workers')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger');
        }

        if ($masters === []) {
            return Stat::make('Queue', $queue_label)
                ->description('Horizon is not running')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color('danger');
        }

        if (collect($masters)->contains(static fn (object $master): bool => $master->status === 'paused')) {
            return Stat::make('Queue', $queue_label)
                ->description('Horizon is paused')
                ->descriptionIcon('heroicon-o-pause-circle')
                ->color('warning');
        }

        return Stat::make('Queue', $queue_label)
            ->description('Horizon is running')
            ->descriptionIcon('heroicon-o-check-circle')
            ->color('success');
    }

    private function searchStat(): Stat
    {
        $search_driver = config('scout.driver');

        if (! is_string($search_driver) || $search_driver === '' || $search_driver === 'null') {
            return Stat::make('Search', 'None')
                ->description('Search is disabled')
                ->descriptionIcon('heroicon-o-minus-circle')
                ->color('gray');
        }

        $search_label = ucfirst($search_driver);

        try {
            $engine = resolve(EngineManager::class)->engine();
            $search_works = $engine instanceof ISearchEngine && $engine->ping();
        } catch (Throwable) {
            $search_works = false;
        }

        return Stat::make('Search', $search_label)
            ->description($search_works ? 'Search engine is working' : 'Search engine is not reachable')
            ->descriptionIcon($search_works ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->color($search_works ? 'success' : 'danger');
    }
}
