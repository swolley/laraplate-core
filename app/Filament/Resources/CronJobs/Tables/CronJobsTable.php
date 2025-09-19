<?php

namespace Modules\Core\Filament\Resources\CronJobs\Tables;

use \Override;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\BaseTable;
use Modules\Core\Models\CronJob;

final class CronJobsTable extends BaseTable
{
    #[Override]
    protected function getModel(): string
    {
        return CronJob::class;
    }

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    ToggleColumn::make('is_active')
                        ->toggleable()
                        ->sortable()
                        ->grow(false)
                        ->alignCenter(),
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
                        ->formatStateUsing(function (CronJob $record) {
                            return sprintf(
                                'Last run: %s<br>Next run: %s',
                                $record->last_run_at?->format('Y-m-d H:i:s'),
                                $record->next_run_at?->format('Y-m-d H:i:s')
                            );
                        })
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->grow(false)
                        ->html(),
                ]);
            },
            actions: function (Collection $default_actions) {
                $default_actions->unshift(Action::make('run')
                    ->icon('heroicon-o-play')
                    ->action(fn(CronJob $record) => $record->run())
                    ->requiresConfirmation());
            },
            filters: function (Collection $default_filters) {
                $default_filters->unshift(
                    TernaryFilter::make('is_active')
                        ->label('Active')
                        ->attribute('is_active')
                        ->nullable(),
                );
            },
        );
    }
}
