<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Widgets;

use Filament\Widgets\Widget;

final class WelcomeLinkWidget extends Widget
{
    protected string $view = 'core::filament.widgets.welcome-link';

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'welcome_url' => \Modules\Core\Filament\Pages\Documentation\Welcome::getUrl(),
        ];
    }
}
