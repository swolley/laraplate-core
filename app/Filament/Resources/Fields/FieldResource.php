<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields;

use BackedEnum;
// use Filament\Resources\Resource;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Core\Filament\Resources\Fields\Pages\CreateField;
use Modules\Core\Filament\Resources\Fields\Pages\EditField;
use Modules\Core\Filament\Resources\Fields\Pages\ListFields;
use Modules\Core\Filament\Resources\Fields\Schemas\FieldForm;
use Modules\Core\Filament\Resources\Fields\Tables\FieldsTable;
use Modules\Core\Models\Field;
use Override;
use UnitEnum;

final class FieldResource extends Resource
{
    #[Override]
    protected static ?string $model = Field::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 6;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'cms/fields';
    }

    public static function form(Schema $schema): Schema
    {
        return FieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FieldsTable::configure($table);
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
            'index' => ListFields::route('/'),
            'create' => CreateField::route('/create'),
            'edit' => EditField::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
