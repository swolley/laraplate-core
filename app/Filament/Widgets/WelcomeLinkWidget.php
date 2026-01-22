<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Modules\Core\Filament\Pages\Welcome;

final class WelcomeLinkWidget extends Widget
{
    protected string $view = 'core::filament.widgets.welcome-link';

    protected int|string|array $columnSpan = 1;

    protected static bool $isHidden = true;

    public static function canView(): bool
    {
        // Hide by default - only show when explicitly registered in dashboard
        // This prevents auto-discovery from showing it everywhere
        return false;
    }

    protected function getViewData(): array
    {
        return [
            'welcome_url' => Welcome::getUrl(),
        ];
    }
}
