<?php

namespace Modules\Core\Filament\Resources\Settings\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Setting;

class SettingsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    IconColumn::make('is_public')
                        ->boolean()
                        ->alignCenter()
                        ->toggleable(isToggledHiddenByDefault: true),
                    IconColumn::make('is_encrypted')
                        ->boolean()
                        ->alignCenter()
                        ->trueIcon('heroicon-o-key')
                        ->toggleable(isToggledHiddenByDefault: false),
                    TextColumn::make('group_name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('type')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('value')
                        ->alignCenter(),
                ]);
            },
            filters: function (Collection $default_filters) {
                $default_filters->unshift(...[
                    SelectFilter::make('type')
                        ->options([
                            'string' => 'String',
                            'integer' => 'Integer',
                            'float' => 'Float',
                            'boolean' => 'Boolean',
                            'array' => 'Array',
                            'json' => 'JSON',
                            'date' => 'Date',
                            'datetime' => 'DateTime',
                        ]),
                    SelectFilter::make('group_name')
                        ->options(fn() => Setting::distinct()->pluck('group_name', 'group_name')->toArray()),
                    SelectFilter::make('is_public')
                        ->options([
                            '1' => 'Public',
                            '0' => 'Private',
                        ]),
                    SelectFilter::make('is_encrypted')
                        ->options([
                            '1' => 'Encrypted',
                            '0' => 'Not Encrypted',
                        ]),
                ]);
            },
        );
    }
}
