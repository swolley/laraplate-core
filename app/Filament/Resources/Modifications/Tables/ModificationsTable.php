<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Helpers\HasApprovals;

final class ModificationsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        $table = self::configureTable(
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

        $models = models(filter: fn (string $model): bool => class_uses_trait($model, HasApprovals::class));

        $table->groups(array_map(fn (string $model): Group => Group::make($model . '.name')->label($model), $models));

        return $table;
    }
}
