<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modules\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Module;

final class ModulesTable
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
                    TextColumn::make('url')
                        ->url(static fn (?string $state): ?string => $state)
                        ->openUrlInNewTab()
                        ->toggleable(),
                ]);

                // The activation icon becomes a switch. Core is always on, so its switch is locked.
                $default_columns->transform(static fn (Column $column): Column => $column->getName() === Module::activationColumn()
                    ? self::toggleColumn(Module::activationColumn())
                        ->toggleable(isToggledHiddenByDefault: false)
                        ->disabled(static fn (Module $record): bool => $record->name === Module::CORE || ! self::canUpdateRecord($record))
                    : $column);
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->each(static function (Action $action): void {
                    if (in_array($action->getName(), ['activate', 'deactivate', 'edit'], true)) {
                        $action->hidden(static fn (Module $record): bool => $record->name === Module::CORE);
                    }
                });
            },
        );
    }
}
