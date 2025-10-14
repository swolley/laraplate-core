<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class CronJobForm
{
    public static function configure(Schema $schema): Schema
    {
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
                    ->maxLength(255),
                TextInput::make('schedule')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Cron expression (e.g. * * * * *)'),
                TextInput::make('description')
                    ->maxLength(255),
                Toggle::make('without_overlapping')
                    ->required()
                    ->default(false),
                TextInput::make('timeout')
                    ->numeric()
                    ->default(60)
                    ->helperText('Timeout in seconds'),
                TextInput::make('tries')
                    ->numeric()
                    ->default(1)
                    ->helperText('Number of retries'),
                TextInput::make('retry_after')
                    ->numeric()
                    ->default(90)
                    ->helperText('Seconds to wait before retry'),
            ]);
    }
}
