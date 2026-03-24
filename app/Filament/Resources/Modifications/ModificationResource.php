<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
// use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
// use Modules\Core\Filament\Resources\Modifications\Pages\CreateModification;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Filament\Resources\Modifications\Pages\EditModification;
use Modules\Core\Filament\Resources\Modifications\Pages\ListModifications;
use Modules\Core\Filament\Resources\Modifications\Schemas\ModificationForm;
use Modules\Core\Filament\Resources\Modifications\Tables\ModificationsTable;
use Modules\Core\Models\Modification;
use Override;
use UnitEnum;

final class ModificationResource extends Resource
{
    #[Override]
    protected static ?string $model = Modification::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandThumbUp;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 6;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/modifications';
    }

    public static function form(Schema $schema): Schema
    {
        return ModificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModificationsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('modifier'));
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
            'index' => ListModifications::route('/'),
            // 'create' => CreateModification::route('/create'),
            'edit' => EditModification::route('/{record}/edit'),
        ];
    }
}
