<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Models\Field;
use Modules\Core\Filament\Utils\HasTable;
use Illuminate\Database\Eloquent\Builder;

final class FieldsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $columns): void {
                $columns->unshift(...[
                    TextColumn::make('name')
                        ->searchable(),
                    TextColumn::make('type')
                        ->searchable(),
                    TextColumn::make('options')
                        ->formatStateUsing(static function (Field $record): string {
                            $string = '';

                            foreach ($record->options as $key => $value) {
                                $string .= sprintf(
                                    '<div class="flex justify-between">
                                    <span>%s:</span>
                                    <span>%s</span>
                                    </div>',
                                    $key,
                                    $value,
                                );
                            }

                            return sprintf('<div class="space-y-1">%s</div', $string);
                        })
                        ->toggleable(isToggledHiddenByDefault: false)
                        ->html(),
                    IconColumn::make('is_slug')
                        ->boolean()
                        ->alignCenter()
                        ->grow(false)
                        ->toggleable(isToggledHiddenByDefault: true),
                ]);
            },
        )
            ->defaultSort(static fn (Builder $query): Builder => $query
                ->orderBy('name'));
    }
}
