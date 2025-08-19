<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\ACLResource\Pages;
use Modules\Core\Models\ACL;

class ACLResource extends Resource
{
    protected static ?string $model = ACL::class;

    protected static ?string $label = 'ACLs';

    protected static ?string $navigationLabel = 'ACLs';

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Core';

    protected static ?int $navigationSort = 4;

    public static function getSlug(): string
    {
        return 'core/acls';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('permission_id')
                    ->relationship('permission', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\KeyValue::make('filters')
                    ->keyLabel('Field')
                    ->valueLabel('Value')
                    ->required(),
                Forms\Components\KeyValue::make('sort')
                    ->keyLabel('Property')
                    ->valueLabel('Direction')
                    ->helperText('Direction can be: asc, desc, ASC, DESC'),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('permission.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->toggleable(),
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
                Tables\Filters\SelectFilter::make('permission')
                    ->relationship('permission', 'name')
                    ->searchable()
                    ->preload(),
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
            'index' => Pages\ListACLs::route('/'),
            'create' => Pages\CreateACL::route('/create'),
            'edit' => Pages\EditACL::route('/{record}/edit'),
        ];
    }
}
