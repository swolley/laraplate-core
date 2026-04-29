<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Casts\FieldType;

final class FieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('type')
                    ->options(FieldType::class)
                    ->required(),
                TextInput::make('options')
                    ->required(),
                Toggle::make('is_slug')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_deleted')
                    ->required(),
            ]);
    }
}
