<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

final class CoreStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::query()->count())
                ->description('Total registered users')
                ->descriptionIcon('heroicon-o-users')
                ->color('primary'),
            // Stat::make('Roles', Role::query()->count())
            //     ->description('User roles')
            //     ->descriptionIcon('heroicon-o-shield-check')
            //     ->color('success'),
            // Stat::make('Permissions', Permission::query()->count())
            //     ->description('System permissions')
            //     ->descriptionIcon('heroicon-o-key')
            //     ->color('info'),
        ];
    }
}
