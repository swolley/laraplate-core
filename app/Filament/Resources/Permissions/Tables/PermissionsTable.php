<?php

namespace Modules\Core\Filament\Resources\Permissions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Permission;

class PermissionsTable
{
    use HasTable;

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
                        ->searchable(),
                    TextColumn::make('connection_name')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('table_name')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('description')
                        ->searchable(),
                ]);
            },
            filters: function (Collection $default_filters) {
                $default_filters->unshift(...[
                    SelectFilter::make('guard_name')
                        ->options(Permission::distinct('guard_name')->pluck('guard_name'))
                        ->multiple()
                        ->preload(),
                    SelectFilter::make('connection_name')
                        ->options(Permission::distinct('connection_name')->pluck('connection_name'))
                        ->preload(),
                    SelectFilter::make('table_name')
                        ->options(Permission::distinct('table_name')->pluck('table_name'))
                        ->preload(),
                ]);
            },
        );
    }
}
