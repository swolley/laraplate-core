<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class RolesTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
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
                        ->toggleable()
                        ->toggleable(isToggledHiddenByDefault: false),
                    TextColumn::make('permissions.name')
                        ->badge()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]);
            },
            filters: static function (Collection $default_filters): void {
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
