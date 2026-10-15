<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Override;

final class HorizonStatsWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 11;

    #[Override]
    protected ?string $pollingInterval = null;

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

        $metrics = resolve(MetricsRepository::class);
        $jobs = resolve(JobRepository::class);

        $pendingCount = (int) $jobs->countPending();
        $failedCount = (int) $jobs->countRecentlyFailed();

        // Throughput (jobs per minute)
        $throughput = method_exists($metrics, 'throughput') ? $metrics->throughput() : 0;

        $stats[] = Stat::make(__('app.widgets.pendingJobs'), $pendingCount)
            ->description(__('app.widgets.pendingJobsHint'))
            ->descriptionIcon('heroicon-o-clock')
            ->color($pendingCount > 100 ? 'warning' : 'primary');

        $stats[] = Stat::make(__('app.widgets.failedJobs'), $failedCount)
            ->description(__('app.widgets.failedJobsHint'))
            ->descriptionIcon('heroicon-o-x-circle')
            ->color($failedCount > 0 ? 'danger' : 'success');

        try {
            $stats[] = Stat::make(__('app.widgets.throughput'), number_format($throughput))
                ->description(__('app.widgets.throughputHint'))
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('primary')
                ->descriptionColor('core');
        } catch (Exception) {
            // Horizon might not be fully configured
            $stats[] = Stat::make(__('app.widgets.horizonStatus'), __('app.widgets.notAvailable'))
                ->description(__('app.widgets.horizonNotConfigured'))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('gray');
        }

        return $stats;
    }
}
