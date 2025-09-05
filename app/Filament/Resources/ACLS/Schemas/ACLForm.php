<?php

namespace Modules\Core\Filament\Resources\ACLS\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ACLForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('permission_id')
                    ->relationship('permission', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                KeyValue::make('filters')
                    ->keyLabel('Field')
                    ->valueLabel('Value')
                    ->required(),
                KeyValue::make('sort')
                    ->keyLabel('Property')
                    ->valueLabel('Direction')
                    ->helperText('Direction can be: asc, desc, ASC, DESC'),
                TextInput::make('description')
                    ->maxLength(255),
            ]);
    }
}
