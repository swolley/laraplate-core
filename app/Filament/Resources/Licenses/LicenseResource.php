<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Licenses\Pages\CreateLicense;
use Modules\Core\Filament\Resources\Licenses\Pages\EditLicense;
use Modules\Core\Filament\Resources\Licenses\Pages\ListLicenses;
use Modules\Core\Filament\Resources\Licenses\Schemas\LicenseForm;
use Modules\Core\Filament\Resources\Licenses\Tables\LicensesTable;
use Modules\Core\Models\License;
use UnitEnum;

final class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 6;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/licenses';
    }

    public static function form(Schema $schema): Schema
    {
        return LicenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LicensesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLicenses::route('/'),
            'create' => CreateLicense::route('/create'),
            'edit' => EditLicense::route('/{record}/edit'),
        ];
    }
}
