<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\SettingResource\Pages;
use Modules\Core\Models\Setting;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Core';

    protected static ?int $navigationSort = 7;

    public static function getSlug(): string
    {
        return 'core/settings';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->regex('/^[a-z0-9_]+$/')
                    ->helperText('Only lowercase letters, numbers and underscores are allowed'),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
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
                Forms\Components\TextInput::make('value')
                    ->required()
                    ->maxLength(65535),
                Forms\Components\TextInput::make('group')
                    ->required()
                    ->maxLength(255)
                    ->default('general'),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_public')
                    ->required()
                    ->default(false)
                    ->helperText('If true, this setting will be accessible via API'),
                Forms\Components\Toggle::make('is_encrypted')
                    ->required()
                    ->default(false)
                    ->helperText('If true, this setting value will be encrypted in database'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('group')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_encrypted')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
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
                Tables\Filters\SelectFilter::make('group')
                    ->options(fn() => Setting::distinct()->pluck('group', 'group')->toArray()),
                Tables\Filters\SelectFilter::make('is_public')
                    ->options([
                        '1' => 'Public',
                        '0' => 'Private',
                    ]),
                Tables\Filters\SelectFilter::make('is_encrypted')
                    ->options([
                        '1' => 'Encrypted',
                        '0' => 'Not Encrypted',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
