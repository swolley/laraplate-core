<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Horizon\Contracts\JobRepository;

// use Laravel\Horizon\Contracts\MetricsRepository;

final class HorizonStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return class_exists(\Laravel\Horizon\HorizonServiceProvider::class);
    }

    protected function getStats(): array
    {
        if (! class_exists(\Laravel\Horizon\HorizonServiceProvider::class)) {
            return [];
        }

        $stats = [];

        try {
            // $metrics = resolve(MetricsRepository::class);
            $jobs = resolve(JobRepository::class);

            // Pending jobs
            $recent = $jobs->getRecent();
            $pendingCount = isset($recent['pending']) && is_countable($recent['pending']) ? count($recent['pending']) : 0;

            // Failed jobs
            $failedCount = isset($recent['failed']) && is_countable($recent['failed']) ? count($recent['failed']) : 0;

            // Throughput (jobs per minute)
            // $throughput = method_exists($metrics, 'throughput') ? $metrics->throughput() : 0;

            $stats[] = Stat::make('Pending Jobs', $pendingCount)
                ->description('Jobs waiting to be processed')
                ->descriptionIcon('heroicon-o-clock')
                ->color($pendingCount > 100 ? 'warning' : 'primary');

            $stats[] = Stat::make('Failed Jobs', $failedCount)
                ->description('Jobs that failed to process')
                ->descriptionIcon('heroicon-o-x-circle')
                ->color($failedCount > 0 ? 'danger' : 'success');

            // $stats[] = Stat::make('Throughput', number_format($throughput))
            //     ->description('Jobs per minute')
            //     ->descriptionIcon('heroicon-o-arrow-trending-up')
            //     ->color('info');
        } catch (Exception $e) {
            // Horizon might not be fully configured
            $stats[] = Stat::make('Horizon Status', 'Not Available')
                ->description('Horizon is not properly configured')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('gray');
        }

        return $stats;
    }
}
