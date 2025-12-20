<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;

final class LicensesTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    IconColumn::make('is_active')
                        ->boolean()
                        ->alignCenter()
                        ->grow(false)
                        ->state(static fn ($record): bool => ! $record->isExpired() && ! $record->isDraft())
                        ->trueColor(static fn ($record): string => $record->isValid() ? 'success' : ($record->isDraft() ? 'gray' : 'warning'))
                        ->trueIcon(static fn ($record): string => $record->isValid() ? 'heroicon-o-check-circle' : ($record->isDraft() ? 'heroicon-o-clock' : 'heroicon-o-exclamation-triangle'))
                        ->falseIcon('heroicon-o-x-circle')
                        ->tooltip(static fn ($record): string => $record->isDraft() ? 'Waiting' : ($record->isExpired() ? 'Expired' : 'Valid')),
                    TextColumn::make('id')
                        ->searchable(),
                ]);
            },
        );
    }
}
