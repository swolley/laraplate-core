<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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
        $model = self::getResource()::getModel();

        $counts_by_type = $model::query()
            ->selectRaw('modifiable_type as type, count(*) as count')
            ->groupBy('modifiable_type')
            ->pluck('count', 'type')
            ->all();

        $total = (int) array_sum($counts_by_type);

        $tabs = [
            'all' => Tab::make('All')->badge($total),
        ];

        $types = models(filter: fn (string $type): bool => class_uses_trait($type, HasApprovals::class));

        foreach ($types as $type) {
            $count = (int) ($counts_by_type[$type] ?? 0);
            $tabs[$type] = Tab::make(Str::afterLast($type, '\\'))
                ->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('modifiable_type', $type));
        }

        return $tabs;
    }
}
