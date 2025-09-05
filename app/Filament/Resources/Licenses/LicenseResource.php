<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
// use Filament\Forms\Components\Toggle;
// use Filament\Resources\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\Licenses\Pages\CreateLicense;
use Modules\Core\Filament\Resources\Licenses\Pages\EditLicense;
use Modules\Core\Filament\Resources\Licenses\Pages\ListLicenses;
use Modules\Core\Models\License;
use UnitEnum;

class LicenseResource extends Resource
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
        return $schema
            ->components([
                // Toggle::make('is_active')
                //     ->required()
                //     ->default(true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->url(),
                TextInput::make('email')
                    ->required()
                    ->maxLength(255)
                    ->email(),
                TextInput::make('company')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('city')
                    ->maxLength(255),
                TextInput::make('state')
                    ->maxLength(255),
                TextInput::make('zip')
                    ->maxLength(255),
                TextInput::make('country')
                    ->maxLength(255),
                DatePicker::make('expires_at')
                    ->required(),
                Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_active')
                    ->boolean()
                    ->state(fn($record) => $record->isValid()),
                TextColumn::make('id')
                    ->searchable(),
                TextColumn::make('valid_from')
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('valid_to')
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
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
                Filter::make('expired')
                    ->query(fn($query) => $query->where('valid_to', '<', now())),
                Filter::make('expiring_soon')
                    ->query(fn($query) => $query->whereBetween('valid_to', [now(), now()->addDays(30)])),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('validate')
                    ->icon('heroicon-o-check-circle')
                    ->action(fn(License $record) => $record->validate())
                    ->requiresConfirmation(),
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
            'index' => ListLicenses::route('/'),
            'create' => CreateLicense::route('/create'),
            'edit' => EditLicense::route('/{record}/edit'),
        ];
    }
}
