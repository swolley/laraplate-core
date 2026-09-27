<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Filament\Resources\Settings\Pages\ListSettings;
use Modules\Core\Filament\Resources\Settings\Schemas\SettingForm;
use Modules\Core\Filament\Resources\Settings\Tables\SettingsTable;
use Modules\Core\Models\Setting;
use Modules\Core\Services\ForcedVersionStrategySettings;
use Override;
use UnitEnum;

final class SettingResource extends Resource
{
    #[Override]
    protected static ?string $model = Setting::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 7;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/settings';
    }

    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('name', resolve(ForcedVersionStrategySettings::class)->names());
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
            'index' => ListSettings::route('/'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }

    /**
     * Settings are owned by seeders: the panel may edit them but never create new ones.
     */
    #[Override]
    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Settings are created by seeders.');
    }

    /**
     * Settings are owned by seeders: the panel never deletes them.
     */
    #[Override]
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Settings are managed by seeders.');
    }

    #[Override]
    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Settings are managed by seeders.');
    }

    #[Override]
    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Settings are managed by seeders.');
    }

    #[Override]
    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Settings are managed by seeders.');
    }
}
