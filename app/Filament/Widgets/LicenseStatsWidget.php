<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Exception;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\License;

final class LicenseStatsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        if (! class_exists(License::class)) {
            return [];
        }

        try {
            $data = DB::table('licenses')->select([
                DB::raw('count(*) as total'),
                // DB::raw('sum(case when users.id is null then 1 else 0 end) as free'),
                // DB::raw('sum(case when valid_to < now() then 1 else 0 end) as expired'),
                DB::raw('sum(case when valid_to >= now() or valid_to is null then 1 else 0 end) as active'),
                DB::raw('sum(case when users.id is not null then 1 else 0 end) as occupied'),
            ])
                ->leftJoin('users', 'licenses.id', '=', 'users.license_id')
                ->first();

            $stats = [];

            if ($data->total > 0) {
                $stats[] = Stat::make('Active Licenses', "{$data->active} / {$data->total}")
                    ->description('Currently valid licenses')
                    ->descriptionIcon('heroicon-o-key')
                    ->color('primary');

                $stats[] = Stat::make('Occupied Licenses', "{$data->occupied} / {$data->active}")
                    ->description('Active sessions')
                    ->descriptionIcon('heroicon-o-user-plus')
                    ->color('info');
            }
        } catch (Exception) {
            // License table might not exist
            return [];
        }

        return $stats;
    }
}
