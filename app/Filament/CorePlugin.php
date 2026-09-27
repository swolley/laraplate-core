<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;
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
     * Register the module colour under the module id, so widgets can paint with it.
     * Unlike the optional modules, Core's navigation group is declared by the panel
     * itself, right after the application groups.
     */
    public function afterRegister(Panel $panel): void
    {
        $color = ModuleColor::filament($this->getModuleName());

        if ($color !== null) {
            $panel->colors([$this->getId() => $color]);
        }
    }
}
