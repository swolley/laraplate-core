<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;
use Modules\Core\Rules\CronParametersList;

final class CronJobForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('command')
                    ->required()
                    ->maxLength(255)
                    ->rule('regex:/^\S+$/')
                    ->helperText(__('app.help.cronCommand')),
                TagsInput::make('parameters')
                    ->default([])
                    ->placeholder(__('app.help.cronParametersPlaceholder'))
                    ->helperText(__('app.help.cronParameters'))
                    ->rule(new CronParametersList()),
                TextInput::make('schedule')
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('app.help.cronSchedule')),
                TextInput::make('description')
                    ->maxLength(255),
                Toggle::make('on_one_server')
                    ->required()
                    ->default(true)
                    ->helperText(__('app.help.cronOnOneServer')),
                Toggle::make('without_overlapping')
                    ->required()
                    ->default(true)
                    ->helperText(__('app.help.cronWithoutOverlapping')),
            ]);
    }
}
