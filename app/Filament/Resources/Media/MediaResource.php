<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Media;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\Resources\Media\Pages\ListMedia;
use Modules\Core\Filament\Resources\Media\Pages\ViewMedia;
use Modules\Core\Filament\Resources\Media\Schemas\MediaInfolist;
use Modules\Core\Filament\Resources\Media\Tables\MediaTable;
use Modules\Core\Models\Media;
use Modules\Core\Models\MediaDraft;
use Override;
use UnitEnum;

/**
 * Read-only media gallery (M22): browse and inspect every claimed media across
 * owners, filter by type/collection/owner, and view its Core metadata plus any
 * analysis panel a module contributes through the
 * {@see \Modules\Core\Filament\ResourceSchemaContributorRegistry}. Media are born
 * from an owner's upload, so the resource offers no create and no edit — display
 * fields are curated on the owning record. Draft-staged media are excluded, so the
 * gallery mirrors the claimed set that participates in search (M14). Realizes the
 * owner-agnostic `open` gallery mode of M16; not a DAM (M15).
 */
final class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 85;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/media';
    }

    public static function infolist(Schema $schema): Schema
    {
        return MediaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaTable::configure($table);
    }

    /**
     * Only claimed media belong in the gallery: while staged, a media is owned by a
     * {@see MediaDraft} and must not surface (M14).
     */
    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('model_type', [(new MediaDraft())->getMorphClass()]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'view' => ViewMedia::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
