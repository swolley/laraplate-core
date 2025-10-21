<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Casts\SettingTypeEnum;

final class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        $typeOptions = [];
        foreach (SettingTypeEnum::cases() as $case) {
            $typeOptions[$case->value] = $case->name;
        }

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('group_name')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (): array => $schema->model::query()->select('group_name')->distinct()->pluck('group_name', 'group_name')->toArray())
                    ->default('general'),
                Select::make('type')
                    ->required()
                    ->options($typeOptions)
                    ->default(SettingTypeEnum::STRING->value),
                TextInput::make('value')
                    ->required()
                    ->maxLength(65535),
                TextInput::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
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
