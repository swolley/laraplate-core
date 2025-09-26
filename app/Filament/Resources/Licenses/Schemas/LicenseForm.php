<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Licenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LicenseForm
{
    public static function configure(Schema $schema): Schema
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
}
