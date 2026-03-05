<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Filament\Pages\Dashboard;
use Filament\Widgets\Widget;
use Modules\Core\Filament\Pages\Welcome;
use Override;

final class WelcomeLinkWidget extends Widget
{
    #[Override]
    protected string $view = 'core::filament.widgets.welcome-link';

    #[Override]
    protected int|string|array $columnSpan = 1;

    private static bool $isHidden = true;

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
