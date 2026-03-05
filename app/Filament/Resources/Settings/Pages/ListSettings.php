<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\Core\Models\Setting;
use Override;

final class ListSettings extends ListRecords
{
    use HasRecords;

    #[Override]
    protected static string $resource = SettingResource::class;

    public function getTabs(): array
    {
        $counts_by_group = Setting::query()->select('group_name')->distinct()->pluck('group_name')->countBy()->toArray();

        if (count($counts_by_group) < 2) {
            return [];
        }

        $counts = array_merge(['all' => (int) array_sum($counts_by_group)], $counts_by_group);

        $tabs = [
            'all' => Tab::make('All')->badge($counts['all']),
        ];

        foreach ($counts_by_group as $group => $count) {
            if ($count === 0) {
                continue;
            }

            $tabs[$group] = Tab::make(ucfirst((string) $group))
                ->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('group_name', $group));
        }

        return $tabs;
    }
}
