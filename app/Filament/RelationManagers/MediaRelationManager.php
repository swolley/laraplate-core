<?php

declare(strict_types=1);

namespace Modules\Core\Filament\RelationManagers;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Models\Media;

/**
 * Curate the Core display metadata (description / alt text / keywords) of the media
 * attached to an owning record (M22). Reusable: any owner resource whose model uses
 * {@see \Modules\Core\Helpers\HasMedia} adds this to `getRelations()`. Editing is
 * gated by the owner resource's own edit access (this lives on the owner's page), so
 * no separate media permission is introduced.
 *
 * Only the display fields are editable, and they live inside the Spatie
 * `custom_properties` JSON: {@see applyDisplayEdit()} merges them back without
 * touching the technical metadata, `content_hash` or provenance of other fields, and
 * marks each edited field as human-authored so the AI layer never overwrites it (M3c).
 * Upload and removal stay with the owner form's file upload; this is curation only.
 */
final class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Media';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedPhoto;

    /**
     * Merge the edited display fields back into `custom_properties`, preserving every
     * other key and recording each written field as human-authored (M3c). A cleared
     * field is removed along with its provenance so the AI layer may fill it again.
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyDisplayEdit(Media $media, array $data): true
    {
        $custom = $media->custom_properties;
        $provenance = is_array($custom['_provenance'] ?? null) ? $custom['_provenance'] : [];

        foreach (['description', 'alt_text'] as $field) {
            $value = $data[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $custom[$field] = $value;
                $provenance[$field] = 'human';
            } else {
                unset($custom[$field], $provenance[$field]);
            }
        }

        $keywords = self::stringList($data['keywords'] ?? []);

        if ($keywords !== []) {
            $custom['keywords'] = $keywords;
            $provenance['keywords'] = 'human';
        } else {
            unset($custom['keywords'], $provenance['keywords']);
        }

        $custom['_provenance'] = $provenance;
        $media->custom_properties = $custom;
        $media->save();

        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::displayFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('collection_name')
                    ->label('Collection')
                    ->badge(),
                TextColumn::make('description')
                    ->state(static fn (Media $record): string => self::customString($record, 'description'))
                    ->placeholder('—')
                    ->limit(48),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit metadata')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema(self::displayFields())
                    ->fillForm(static fn (Media $record): array => [
                        'description' => self::customString($record, 'description'),
                        'alt_text' => self::customString($record, 'alt_text'),
                        'keywords' => self::customList($record, 'keywords'),
                    ])
                    ->action(static fn (array $data, Media $record): true => self::applyDisplayEdit($record, $data)),
            ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Field>
     */
    private static function displayFields(): array
    {
        return [
            TextInput::make('description')
                ->maxLength(1000),
            TextInput::make('alt_text')
                ->label('Alt text')
                ->maxLength(255),
            TagsInput::make('keywords'),
        ];
    }

    private static function customString(Media $media, string $key): string
    {
        $value = $media->custom_properties[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    private static function customList(Media $media, string $key): array
    {
        return self::stringList($media->custom_properties[$key] ?? []);
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
