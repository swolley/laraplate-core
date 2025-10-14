<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class ModificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('modifiable_id')
                    ->numeric(),
                TextInput::make('modifiable_type'),
                TextInput::make('modifier_id')
                    ->numeric(),
                TextInput::make('modifier_type'),
                Toggle::make('active')
                    ->required(),
                Toggle::make('is_update')
                    ->required(),
                TextInput::make('approvers_required')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('disapprovers_required')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('md5')
                    ->required(),
                TextInput::make('modifications')
                    ->required(),
            ]);
    }
}
