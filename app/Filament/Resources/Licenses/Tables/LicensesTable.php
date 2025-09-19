<?php

namespace Modules\Core\Filament\Resources\Licenses\Tables;

use \Override;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\BaseTable;
use Modules\Core\Models\License;

class LicensesTable extends BaseTable
{
    #[Override]
    protected function getModel(): string
    {
        return License::class;
    }

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    IconColumn::make('is_active')
                        ->boolean()
                        ->alignCenter()
                        ->grow(false)
                        ->state(fn($record) => !$record->isExpired() && !$record->isDraft())
                        ->trueColor(fn($record) => $record->isValid() ? 'success' : ($record->isDraft() ? 'gray' : 'warning'))
                        ->trueIcon(fn($record) => $record->isValid() ? 'heroicon-o-check-circle' : ($record->isDraft() ? 'heroicon-o-clock' : 'heroicon-o-exclamation-triangle'))
                        ->falseIcon('heroicon-o-x-circle')
                        ->tooltip(fn($record) => $record->isDraft() ? 'Waiting' : ($record->isExpired() ? 'Expired' : 'Valid')),
                    TextColumn::make('id')
                        ->searchable(),
                ]);
            },
        );
    }
}
