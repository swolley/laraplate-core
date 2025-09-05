<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
// use Filament\Resources\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\CronJobs\Pages\CreateCronJob;
use Modules\Core\Filament\Resources\CronJobs\Pages\EditCronJob;
use Modules\Core\Filament\Resources\CronJobs\Pages\ListCronJobs;
use Modules\Core\Models\CronJob;
use UnitEnum;

class CronJobResource extends Resource
{
    protected static ?string $model = CronJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 5;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/cron-jobs';
    }

    public static function form(Schema $schema): Schema
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('command')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('schedule')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('without_overlapping')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_run_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('next_run_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('run')
                    ->icon('heroicon-o-play')
                    ->action(fn(CronJob $record) => $record->run())
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCronJobs::route('/'),
            'create' => CreateCronJob::route('/create'),
            'edit' => EditCronJob::route('/{record}/edit'),
        ];
    }
}
