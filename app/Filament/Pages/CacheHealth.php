<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages;

use BackedEnum;
use Deprecated;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Filament\Widgets\SearchEngineHealthTableWidget;
use Override;
use UnitEnum;

final class CacheHealth extends Page
{
    // protected string $view = 'core::filament.pages.cache';

    #[Override]
    protected static ?string $navigationLabel = 'Caches';

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Health';

    #[Override]
    protected static ?string $slug = 'health/cache';

    // protected static ?int $navigationSort = 1;

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            SearchEngineHealthTableWidget::class,
        ];
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
