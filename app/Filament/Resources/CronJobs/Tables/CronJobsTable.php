<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\CronJob;

final class CronJobsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('command')
                        ->searchable()
                        ->toggleable(),
                    TextColumn::make('schedule')
                        ->searchable()
                        ->grow(false)
                        ->toggleable(),
                    IconColumn::make('without_overlapping')
                        ->boolean()
                        ->grow(false)
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('runs')
                        ->formatStateUsing(fn (CronJob $record): string => sprintf(
                            'Last run: %s<br>Next run: %s',
                            $record->last_run_at?->format('Y-m-d H:i:s'),
                            $record->next_run_at?->format('Y-m-d H:i:s'),
                        ))
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->grow(false)
                        ->html(),
                ]);
            },
            actions: function (Collection $default_actions): void {
                $default_actions->unshift(Action::make('run')
                    ->icon('heroicon-o-play')
                    ->action(fn (CronJob $record) => $record->run())
                    ->requiresConfirmation());
            },
        );
    }
}
