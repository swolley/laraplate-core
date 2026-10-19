<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modules\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Models\Module;

final class ModuleForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                TextInput::make('name')
                    ->disabled()
                    ->dehydrated(false),
                Toggle::make('is_active')
                    ->required()
                    ->disabled(static fn (?Module $record): bool => $record?->name === Module::CORE),
                TextInput::make('url')
                    ->url()
                    ->maxLength(255),
            ]);
    }
}
