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
                    ->label('Entity')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('source_format')
                    ->label('Format')
                    ->badge(),
                TextEntry::make('original_filename')
                    ->label('File'),
                TextEntry::make('user.name')
                    ->label('Started by')
                    ->placeholder('—'),
                TextEntry::make('counters')
                    ->label('Rows (processed / created / updated / skipped / failed)')
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
                    ->label('Detected columns')
                    ->columnSpanFull()
                    ->placeholder('—')
                    ->state(static fn (ImportSession $record): string => implode(', ', $record->detected_columns ?? [])),
                TextEntry::make('mapping')
                    ->label('Mapping (field → column)')
                    ->columnSpanFull()
                    ->placeholder('—')
                    ->state(static fn (ImportSession $record): string => (string) json_encode(
                        $record->mapping ?? [],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    )),
            ]);
    }
}
