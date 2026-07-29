<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Fields\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Casts\FieldType;
use Modules\Core\Filament\Utils\HasForm;

final class FieldForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

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
