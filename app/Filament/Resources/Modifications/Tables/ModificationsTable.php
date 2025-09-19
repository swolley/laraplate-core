<?php

namespace Modules\Core\Filament\Resources\Modifications\Tables;

use \Override;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\BaseTable;
use Modules\Core\Models\Modification;

class ModificationsTable extends BaseTable
{
    #[Override]
    protected function getModel(): string
    {
        return Modification::class;
    }

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    TextColumn::make('modifiable_id')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('modifiable_type')
                        ->searchable(),
                    // TextColumn::make('modifier_id')
                    //     ->numeric()
                    //     ->sortable(),
                    // TextColumn::make('modifier_type')
                    //     ->searchable(),
                    // TextColumn::make('approvers_required')
                    //     ->numeric()
                    //     ->sortable(),
                    // TextColumn::make('disapprovers_required')
                    //     ->numeric()
                    //     ->sortable(),
                    // TextColumn::make('md5')
                    //     ->searchable(),
                    TextColumn::make()
                        ->label('Original')
                        ->limit(50),
                    TextColumn::make('modifications.original')
                        ->limit(50),
                    TextColumn::make('modifications.modified')
                        ->limit(50),
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('updated_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]);
            },
        );
    }
}
