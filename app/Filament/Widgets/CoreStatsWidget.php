<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use LogicException;
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
        $license = $this->configuredModel(new License());
        $user = $this->configuredModel(new User());
        $license_connection = $license->getConnection()->getName();
        $user_connection = $user->getConnection()->getName();
        $cache_key = sprintf(
            'filament.dashboard.core_stats.%s.%s',
            $license_connection,
            $user_connection,
        );

        $data = Cache::remember($cache_key, 60, static function () use ($license, $user): array {
            return [
                'users' => $user->newQuery()->count(),
                'total' => $license->newQuery()->count(),
                'active' => $license->newQuery()
                    ->where(static fn ($query) => $query
                        ->whereNull('valid_to')
                        ->orWhere('valid_to', '>=', now()))
                    ->count(),
                'occupied' => $user->newQuery()
                    ->whereNotNull('license_id')
                    ->distinct()
                    ->count('license_id'),
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

    /**
     * Resolve dashboard model prototypes from trusted application configuration.
     *
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function configuredModel(Model $model): Model
    {
        $connection = config('core.model_connections.' . $model::class);

        if ($connection === null) {
            return $model;
        }

        $connections = config('database.connections', []);

        if (! is_string($connection)
            || $connection === ''
            || ! is_array($connections)
            || ! array_key_exists($connection, $connections)) {
            throw new LogicException('Core model connection is not configured.');
        }

        return $model->setConnection($connection);
    }
}
