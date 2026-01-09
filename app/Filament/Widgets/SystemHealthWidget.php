<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class SystemHealthWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $stats = [];

        // Cache status
        try {
            $cache_key = 'system_health_check_' . time();
            Cache::put($cache_key, 'ok', 10);
            $cache_works = Cache::get($cache_key) === 'ok';
            Cache::forget($cache_key);

            $stats[] = Stat::make('Cache', $cache_works ? 'Active' : 'Inactive')
                ->description($cache_works ? 'Cache is working' : 'Cache is not working')
                ->descriptionIcon($cache_works ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color($cache_works ? 'success' : 'danger');
        } catch (Exception) {
            $stats[] = Stat::make('Cache', 'Error')
                ->description('Unable to check cache status')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('gray');
        }

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

        // Queue status
        try {
            $queue_driver = config('queue.default', 'sync');
            $queue_works = $queue_driver !== 'sync';

            $stats[] = Stat::make('Queue', ucfirst($queue_driver))
                ->description($queue_works ? 'Queue is active' : 'Queue is synchronous')
                ->descriptionIcon($queue_works ? 'heroicon-o-queue-list' : 'heroicon-o-clock')
                ->color($queue_works ? 'success' : 'gray');
        } catch (Exception) {
            $stats[] = Stat::make('Queue', 'Unknown')
                ->description('Unable to check queue status')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('gray');
        }

        return $stats;
    }
}
