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
                    ->helperText('The name of the command only, as artisan lists it (e.g. model:prune). Its arguments go in the parameters.'),
                TagsInput::make('parameters')
                    ->default([])
                    ->placeholder('--all, --limit=10 or an argument')
                    ->helperText('One element each: a flag (--all), an option with a value (--limit=10) or an argument. A value must not start with a dash; a short option with a value takes two elements (-n, 5). A value of @now or @today is replaced by that date, as ISO 8601, each time the job runs.')
                    ->rule(new CronParametersList()),
                TextInput::make('schedule')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Cron expression (e.g. * * * * *)'),
                TextInput::make('description')
                    ->maxLength(255),
                Toggle::make('on_one_server')
                    ->required()
                    ->default(true)
                    ->helperText('Run on a single server when several share the scheduler.'),
                Toggle::make('without_overlapping')
                    ->required()
                    ->default(true)
                    ->helperText('Skip a run while the previous one is still running.'),
            ]);
    }
}
