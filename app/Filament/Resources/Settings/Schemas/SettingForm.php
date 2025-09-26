<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
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
}
