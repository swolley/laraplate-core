<?php

declare(strict_types=1);

use Filament\Panel;
use Modules\Core\Filament\CorePlugin;

it('exposes core plugin metadata and boots without error', function (): void {
    $plugin = new CorePlugin();

    expect($plugin->getId())->toBe('core')
        ->and($plugin->getModuleName())->toBe('Core');

    $plugin->boot(Panel::make('core-test'));
});
