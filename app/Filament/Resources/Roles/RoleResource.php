<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Roles;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Roles\Pages\CreateRole;
use Modules\Core\Filament\Resources\Roles\Pages\EditRole;
use Modules\Core\Filament\Resources\Roles\Pages\ListRoles;
use Modules\Core\Filament\Resources\Roles\Schemas\RoleForm;
use Modules\Core\Filament\Resources\Roles\Tables\RolesTable;
use Modules\Core\Models\Role;
use Override;
use UnitEnum;

final class RoleResource extends Resource
{
    #[Override]
    protected static ?string $model = Role::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 2;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/roles';
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table)
            ->modifyQueryUsing(fn ($query) => $query->with('permissions'))
            ->defaultSort('name');
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
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
