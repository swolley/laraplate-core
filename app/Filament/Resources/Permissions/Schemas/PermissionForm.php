<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->regex('/^\\w+\\.\\w+\\.\\w+$/')
                    ->helperText('Format: module.model.action'),
                TextInput::make('guard_name')
                    ->required()
                    ->maxLength(255)
                    ->default('web'),
                TextInput::make('description')
                    ->maxLength(255),
            ]);
    }
}
