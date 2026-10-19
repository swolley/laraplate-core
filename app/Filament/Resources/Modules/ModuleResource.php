<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modules;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\Modules\Pages\EditModule;
use Modules\Core\Filament\Resources\Modules\Pages\ListModules;
use Modules\Core\Filament\Resources\Modules\Schemas\ModuleForm;
use Modules\Core\Filament\Resources\Modules\Tables\ModulesTable;
use Modules\Core\Filament\Utils\HasTranslatedResourceLabels;
use Modules\Core\Models\Module;
use Override;
use UnitEnum;

final class ModuleResource extends Resource
{
    use HasTranslatedResourceLabels;

    #[Override]
    protected static ?string $model = Module::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'app.navigation.core';

    #[Override]
    protected static ?int $navigationSort = 8;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/modules';
    }

    public static function form(Schema $schema): Schema
    {
        return ModuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModulesTable::configure($table);
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
            'index' => ListModules::route('/'),
            'edit' => EditModule::route('/{record}/edit'),
        ];
    }

    /**
     * Core is always on and has nothing else to edit.
     */
    #[Override]
    public static function canEdit(Model $record): bool
    {
        return $record->name !== Module::CORE && parent::canEdit($record);
    }

    /**
     * Modules are the ones found on disk: the activator creates the rows, the panel never does.
     */
    #[Override]
    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Modules are created from the modules on disk.');
    }

    #[Override]
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Modules are managed by the module activator.');
    }

    #[Override]
    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Modules are managed by the module activator.');
    }

    #[Override]
    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Modules are managed by the module activator.');
    }

    #[Override]
    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Modules are managed by the module activator.');
    }
}
