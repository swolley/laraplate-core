<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Deprecated;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

final class HorizonHealth extends Page
{
    #[Override]
    protected static ?string $navigationLabel = 'Queues';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Health';

    #[Override]
    protected static ?string $slug = 'health/queues';

    #[Override]
    protected string $view = 'core::filament.pages.horizon';

    #[Override]
    public static function canAccess(): bool
    {
        return class_exists(\Laravel\Horizon\HorizonServiceProvider::class);
    }

    // protected static ?int $navigationSort = 1;

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return Filament::getWidgets();
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    #[Deprecated(message: 'Use `getWidgetsSchemaComponents($this->getWidgets())` to transform widgets into schema components instead, which also filters their visibility.')]
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }
}
