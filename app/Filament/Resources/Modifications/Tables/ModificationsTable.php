<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class ModificationsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('modifiable_id')
                        ->numeric()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('modifiable_type')
                        ->searchable(),
                    TextColumn::make('modifier.name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('modifications.original')
                        ->label('Original')
                        ->limit(50),
                    TextColumn::make('modifications.modified')
                        ->label('Modified')
                        ->limit(50),
                ]);
            },
        );
    }
}
