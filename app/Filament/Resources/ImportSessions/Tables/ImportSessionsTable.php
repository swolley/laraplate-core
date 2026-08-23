<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ImportSessions\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Import\Support\ImportLauncher;
use Modules\Core\Models\ImportSession;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportSessionsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(
                    TextColumn::make('entity_key')
                        ->label('Entity')
                        ->badge()
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('original_filename')
                        ->label('File')
                        ->limit(32)
                        ->searchable(),
                    TextColumn::make('source_format')
                        ->label('Format')
                        ->badge(),
                    TextColumn::make('status')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('processed_rows')
                        ->label('Processed')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('failed_rows')
                        ->label('Failed')
                        ->numeric()
                        ->color('danger')
                        ->sortable(),
                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable(),
                );
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->unshift(
                    Action::make('run')
                        ->label('Run')
                        ->icon(Heroicon::OutlinedPlay)
                        ->requiresConfirmation()
                        ->visible(static fn (ImportSession $record): bool => app(ImportLauncher::class)->isLaunchable($record))
                        ->action(static function (ImportSession $record): void {
                            $launcher = app(ImportLauncher::class);
                            $missing = $launcher->missingRequiredFields($record);

                            if ($missing !== []) {
                                Notification::make()
                                    ->title('Mapping incomplete')
                                    ->body('Map every required field first: ' . implode(', ', $missing) . '.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $launcher->queue($record);

                            Notification::make()
                                ->title('Import queued')
                                ->body("Importing {$record->original_filename} in the background.")
                                ->success()
                                ->send();
                        }),
                    Action::make('downloadErrors')
                        ->label('Errors CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->visible(static fn (ImportSession $record): bool => $record->failed_rows > 0)
                        ->action(static fn (ImportSession $record): StreamedResponse => self::errorReport($record)),
                );
            },
        );
    }

    private static function errorReport(ImportSession $record): StreamedResponse
    {
        return response()->streamDownload(function () use ($record): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['row_number', 'field', 'messages', 'raw']);

            $record->rowErrors()->orderBy('row_number')->chunk(500, static function (Collection $errors) use ($handle): void {
                foreach ($errors as $error) {
                    foreach ($error->errors as $field => $messages) {
                        fputcsv($handle, [
                            $error->row_number,
                            $field,
                            implode('; ', (array) $messages),
                            (string) json_encode($error->raw),
                        ]);
                    }
                }
            });

            fclose($handle);
        }, 'import-' . $record->getKey() . '-errors.csv', ['Content-Type' => 'text/csv']);
    }
}
