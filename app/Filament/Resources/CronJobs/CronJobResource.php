<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\CronJobs;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Filament\Resources\CronJobs\Pages\CreateCronJob;
use Modules\Core\Filament\Resources\CronJobs\Pages\EditCronJob;
use Modules\Core\Filament\Resources\CronJobs\Pages\ListCronJobs;
use Modules\Core\Filament\Resources\CronJobs\Schemas\CronJobForm;
use Modules\Core\Filament\Resources\CronJobs\Tables\CronJobsTable;
use Modules\Core\Models\CronJob;
use UnitEnum;

final class CronJobResource extends Resource
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
        return CronJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CronJobsTable::configure($table);
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
