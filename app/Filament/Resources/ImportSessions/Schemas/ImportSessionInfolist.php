<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ImportSessions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Core\Models\ImportSession;

final class ImportSessionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('entity_key')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('source_format')
                    ->badge(),
                TextEntry::make('original_filename'),
                TextEntry::make('user.name')
                    ->label(__('app.fields.started_by'))
                    ->placeholder('—'),
                TextEntry::make('counters')
                    ->state(static fn (ImportSession $record): string => sprintf(
                        '%d / %d / %d / %d / %d',
                        $record->processed_rows,
                        $record->created_rows,
                        $record->updated_rows,
                        $record->skipped_rows,
                        $record->failed_rows,
                    )),
                TextEntry::make('started_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('finished_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('detected_columns')
                    ->columnSpanFull()
                    ->placeholder('—')
                    ->state(static fn (ImportSession $record): string => implode(', ', $record->detected_columns ?? [])),
                TextEntry::make('mapping')
                    ->columnSpanFull()
                    ->placeholder('—')
                    ->state(static fn (ImportSession $record): string => (string) json_encode(
                        $record->mapping ?? [],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    )),
            ]);
    }
}
