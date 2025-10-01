<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Typesense\Override;
use UnitEnum;

class HorizonHealth extends Page
{
    protected static ?string $navigationLabel = 'Queues';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Health';

    protected static ?string $slug = 'health/queues';

    protected string $view = 'core::filament.pages.horizon';

    // protected static ?int $navigationSort = 1;

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return Filament::getWidgets();
    }

    /**
     * @deprecated Use `getWidgetsSchemaComponents($this->getWidgets())` to transform widgets into schema components instead, which also filters their visibility.
     *
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    #[Override]
    public static function canAccess(): bool
    {
        return class_exists('Laravel\Horizon\HorizonServiceProvider');
    }
}
