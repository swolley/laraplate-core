<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

final class CoreStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $data = DB::table('licenses')->select([
            DB::raw('count(*) as total'),
            DB::raw('sum(case when valid_to >= now() or valid_to is null then 1 else 0 end) as active'),
            DB::raw('sum(case when users.id is not null then 1 else 0 end) as occupied'),
        ])
            ->leftJoin('users', 'licenses.id', '=', 'users.license_id')
            ->first();

        return [
            Stat::make('Users', User::query()->count())
                ->description('Total registered users')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Active Licenses', "{$data->active} / {$data->total}")
                ->description('Currently valid licenses')
                ->descriptionIcon('heroicon-o-key')
                ->color('primary'),
            Stat::make('Occupied Licenses', "{$data->occupied} / {$data->active}")
                ->description('Active sessions')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('primary'),
        ];
    }
}
