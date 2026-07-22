<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\CoreTables;
use Modules\Core\Models\License;
use Override;

final class CoreStatsWidget extends BaseWidget
{
    #[Override]
    protected static bool $isLazy = true;

    #[Override]
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $licenses_table = CoreTables::Licenses->value;
        $users_table = CoreTables::Users->value;

        $data = Cache::remember('filament.dashboard.core_stats', 60, static function () use ($licenses_table, $users_table): array {
            $licenses = (new License)->getConnection()->table($licenses_table)->select([
                DB::raw('count(*) as total'),
                DB::raw("coalesce(sum(case when {$licenses_table}.valid_to >= now() or {$licenses_table}.valid_to is null then 1 else 0 end), 0) as active"),
                DB::raw("coalesce(sum(case when {$users_table}.id is not null then 1 else 0 end), 0) as occupied"),
            ])
                ->leftJoin($users_table, "{$licenses_table}.id", '=', "{$users_table}.license_id")
                ->first();

            return [
                'users' => User::query()->count(),
                'total' => $licenses->total,
                'active' => $licenses->active,
                'occupied' => $licenses->occupied,
            ];
        });

        return [
            Stat::make('Users', $data['users'])
                ->description('Total registered users')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            Stat::make('Active Licenses', "{$data['active']} / {$data['total']}")
                ->description('Currently valid licenses')
                ->descriptionIcon('heroicon-o-key')
                ->color('primary'),
            Stat::make('Occupied Licenses', "{$data['occupied']} / {$data['active']}")
                ->description('Active sessions')
                ->descriptionIcon('heroicon-o-user-plus')
                ->color('primary'),
        ];
    }
}
