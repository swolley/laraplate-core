<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\ACLS;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\ACLS\Pages\CreateACL;
use Modules\Core\Filament\Resources\ACLS\Pages\EditACL;
use Modules\Core\Filament\Resources\ACLS\Pages\ListACLs;
use Modules\Core\Filament\Resources\ACLS\Schemas\ACLForm;
use Modules\Core\Filament\Resources\ACLS\Tables\ACLsTable;
use Modules\Core\Models\ACL;
use UnitEnum;

class ACLResource extends Resource
{
    protected static ?string $model = ACL::class;

    protected static ?string $label = 'ACLs';

    protected static ?string $navigationLabel = 'ACLs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 4;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/acls';
    }

    public static function form(Schema $schema): Schema
    {
        return ACLForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ACLsTable::configure($table);
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
            'index' => ListACLs::route('/'),
            'create' => CreateACL::route('/create'),
            'edit' => EditACL::route('/{record}/edit'),
        ];
    }
}
