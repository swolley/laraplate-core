<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Media\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;
use Modules\Core\Filament\ResourceSchemaContributorRegistry;
use Modules\Core\Models\Media;

/**
 * Read-only media view (M22): the Core display and technical metadata, plus a
 * trailing group that renders whatever sections modules contribute for a `Media`
 * through the {@see ResourceSchemaContributorRegistry} (the AI analysis panel is
 * one such contribution). Core references no contributor directly.
 */
final class MediaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('File')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('file_name')
                            ->label('File name'),
                        TextEntry::make('mime_type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('collection_name')
                            ->label('Collection')
                            ->badge(),
                        TextEntry::make('size')
                            ->formatStateUsing(static fn (int $state): string => Number::fileSize($state)),
                        TextEntry::make('owner')
                            ->state(static fn (Media $record): string => self::ownerLabel($record))
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ]),
                Section::make('Display')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->state(static fn (Media $record): string => self::customString($record, 'description'))
                            ->placeholder('—'),
                        TextEntry::make('alt_text')
                            ->label('Alt text')
                            ->state(static fn (Media $record): string => self::customString($record, 'alt_text'))
                            ->placeholder('—'),
                        TextEntry::make('keywords')
                            ->badge()
                            ->state(static fn (Media $record): array => self::customList($record, 'keywords')),
                    ]),
                // A module (AI, for Media) may append read-only analysis sections here
                // through the Core contributor seam; empty until one registers (M22).
                Group::make()
                    ->schema(static fn (Media $record): array => app(ResourceSchemaContributorRegistry::class)->infolistSectionsFor($record)),
            ]);
    }

    private static function ownerLabel(Media $record): string
    {
        if ($record->model_type === '') {
            return '—';
        }

        return class_basename($record->model_type) . ' #' . $record->model_id;
    }

    private static function customString(Media $record, string $key): string
    {
        $value = $record->custom_properties[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private static function customList(Media $record, string $key): array
    {
        $value = $record->custom_properties[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
