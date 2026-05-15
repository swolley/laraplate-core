<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CoreTables;
use Override;

final class CoreStatsWidget extends BaseWidget
{
    #[Override]
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $licenses_table = CoreTables::Licenses->value;
        $users_table = CoreTables::Users->value;

        $data = DB::table($licenses_table)->select([
            DB::raw('count(*) as total'),
            DB::raw('coalesce(sum(case when valid_to >= now() or valid_to is null then 1 else 0 end), 0) as active'),
            DB::raw("coalesce(sum(case when {$users_table}.id is not null then 1 else 0 end), 0) as occupied"),
        ])
            ->leftJoin($users_table, "{$licenses_table}.id", '=', "{$users_table}.license_id")
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
