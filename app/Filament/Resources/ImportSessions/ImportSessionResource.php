<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ImportSessions;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\ImportSessions\Pages\ListImportSessions;
use Modules\Core\Filament\Resources\ImportSessions\Pages\ViewImportSession;
use Modules\Core\Filament\Resources\ImportSessions\Schemas\ImportSessionInfolist;
use Modules\Core\Filament\Resources\ImportSessions\Tables\ImportSessionsTable;
use Modules\Core\Models\ImportSession;
use Override;
use UnitEnum;

/**
 * A monitoring surface for bulk imports: list every import, view its status,
 * counters and mapping, launch a mapped draft, and download the per-row failure
 * report. The interactive upload-and-map wizard lives in the SPA; here an operator
 * watches imports and (re)launches an already-mapped one — the record itself is
 * created by the import API, never hand-edited, so the resource offers no create.
 */
final class ImportSessionResource extends Resource
{
    protected static ?string $model = ImportSession::class;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 80;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/import-sessions';
    }

    public static function infolist(Schema $schema): Schema
    {
        return ImportSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportSessions::route('/'),
            'view' => ViewImportSession::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
