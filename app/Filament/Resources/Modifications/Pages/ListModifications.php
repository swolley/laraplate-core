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

    public function getTabs(): array
    {
        $model = self::getResource()::getModel();

        $tabs = [
            'all' => Tab::make('All')->badge($model::query()->count()),
        ];

        $types = models(filter: fn (string $type): bool => class_uses_trait($type, HasApprovals::class));

        foreach ($types as $type) {
            $tabs[$type] = Tab::make(Str::afterLast($type, '\\'))
                ->badge($model::query()->where('modifiable_type', $type)->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('modifiable_type', $type));
        }

        return $tabs;
    }
}
