<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\Resources\Settings\SettingResource;
use Modules\Core\Filament\Utils\HasRecords;
use Modules\Core\Models\Setting;

final class ListSettings extends ListRecords
{
    use HasRecords;

    protected static string $resource = SettingResource::class;

    public function getTabs(): array
    {
        $groups = Setting::query()->select('group_name')->distinct()->pluck('group_name');

        if ($groups->count() < 2) {
            return [];
        }

        $tabs = [
            'all' => Tab::make('All')->badge(Setting::query()->count()),
        ];

        foreach ($groups as $group) {
            $tabs[$group] = Tab::make($group)
                ->badge(Setting::query()->where('group_name', $group)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('group_name', $group));
        }

        return $tabs;
    }
}
