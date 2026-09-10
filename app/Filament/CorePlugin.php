<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Support\ModuleColor;

final class CorePlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Core';
    }

    public function getId(): string
    {
        return 'core';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }

    /**
     * Own the module's presence in the panel instead of having the panel list every
     * module: the navigation group, its icon and the module colour (registered under the
     * module id, so widgets can paint with it) are all declared here.
     */
    public function afterRegister(Panel $panel): void
    {
        $color = ModuleColor::filament($this->getModuleName());

        if ($color !== null) {
            $panel->colors([$this->getId() => $color]);
        }

        $panel->navigationGroups([
            NavigationGroup::make()
                ->label('Core')
                ->icon(Heroicon::OutlinedBolt),
        ]);
    }
}
