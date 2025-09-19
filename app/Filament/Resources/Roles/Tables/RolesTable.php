<?php

namespace Modules\Core\Filament\Resources\Roles\Tables;

use \Override;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\BaseTable;
use Modules\Core\Models\Role;

class RolesTable extends BaseTable
{
    #[Override]
    protected function getModel(): string
    {
        return Role::class;
    }

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('guard_name')
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('description')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('permissions.name')
                        ->badge()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]);
            },
            filters: function (Collection $default_filters) {
                $default_filters->unshift(...[
                    SelectFilter::make('permissions')
                        ->relationship('permissions', 'name')
                        ->multiple()
                        ->preload(),
                ]);
            },
        );
    }
}
