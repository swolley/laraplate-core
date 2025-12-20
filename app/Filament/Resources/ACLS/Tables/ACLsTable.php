<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class ACLsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('permission.name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('description')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('filters')
                        ->toggleable(),
                    TextColumn::make('sort')
                        ->toggleable(),
                ]);
            },
        );
    }
}
