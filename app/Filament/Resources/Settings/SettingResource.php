<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
// use Filament\Resources\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Settings\Pages\CreateSetting;
use Modules\Core\Filament\Resources\Settings\Pages\EditSetting;
use Modules\Core\Filament\Resources\Settings\Pages\ListSettings;
use Modules\Core\Models\Setting;
use UnitEnum;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 7;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/settings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->required()
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'float' => 'Float',
                        'boolean' => 'Boolean',
                        'array' => 'Array',
                        'json' => 'JSON',
                        'date' => 'Date',
                        'datetime' => 'DateTime',
                    ])
                    ->default('string'),
                TextInput::make('value')
                    ->required()
                    ->maxLength(65535),
                TextInput::make('group_name')
                    ->required()
                    ->maxLength(255)
                    ->default('general'),
                TextInput::make('description')
                    ->maxLength(255),
                Toggle::make('is_public')
                    ->required()
                    ->default(false)
                    ->helperText('If true, this setting will be accessible via API'),
                Toggle::make('is_encrypted')
                    ->required()
                    ->default(false)
                    ->helperText('If true, this setting value will be encrypted in database'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('value')
                    ->searchable()
                    ->limit(50),
                IconColumn::make('is_public')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_encrypted')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'float' => 'Float',
                        'boolean' => 'Boolean',
                        'array' => 'Array',
                        'json' => 'JSON',
                        'date' => 'Date',
                        'datetime' => 'DateTime',
                    ]),
                SelectFilter::make('group_name')
                    ->options(fn() => Setting::distinct()->pluck('group_name', 'group_name')->toArray()),
                SelectFilter::make('is_public')
                    ->options([
                        '1' => 'Public',
                        '0' => 'Private',
                    ]),
                SelectFilter::make('is_encrypted')
                    ->options([
                        '1' => 'Encrypted',
                        '0' => 'Not Encrypted',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'create' => CreateSetting::route('/create'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
