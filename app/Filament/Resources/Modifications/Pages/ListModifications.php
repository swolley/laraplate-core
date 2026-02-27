<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Core\Filament\Resources\Modifications\ModificationResource;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\Core\Helpers\HasApprovals;

final class ListModifications extends ListRecords
{
    use HasRecords;

    protected static string $resource = ModificationResource::class;

    /**
     * Build tabs with badges from a single grouped query instead of N+1 count() queries.
     */
    public function getTabs(): array
    {
        /** @var class-string<Model> $model */
        $model = self::getResource()::getModel();

        // $cache_key = 'filament_core_modifications_tabs_' . $model;

        // $counts = Cache::remember($cache_key, config('core.filament.tabs_counts_ttl_seconds'), function () use ($model) {
        $counts_by_type = $model::query()
            ->selectRaw('modifiable_type, count(*) as count')
            ->groupBy('modifiable_type')
            ->pluck('count', 'modifiable_type')
            ->all();

        $counts = array_merge(['all' => (int) array_sum($counts_by_type)], $counts_by_type);
        // });

        if (count($counts) < 2) {
            return [];
        }

        $tabs = [
            'all' => Tab::make('All')->badge($counts['all']),
        ];

        $types = models(filter: fn (string $type): bool => class_uses_trait($type, HasApprovals::class));

        foreach ($types as $type) {
            $totals = (int) ($counts[$type] ?? 0);

            if ($totals === 0) {
                continue;
            }

            $tabs[$type] = Tab::make(Str::afterLast($type, '\\'))
                ->badge($totals)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('modifiable_type', $type));
        }

        return $tabs;
    }
}
