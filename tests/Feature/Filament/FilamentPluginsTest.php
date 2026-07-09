<?php

declare(strict_types=1);

use Filament\Panel;
use Modules\CMS\Filament\CMSPlugin;
use Modules\Core\Filament\CorePlugin;
use Modules\ERP\Filament\ERPPlugin;

it('exposes core plugin metadata and boots without error', function (): void {
    $plugin = new CorePlugin();

    expect($plugin->getId())->toBe('core')
        ->and($plugin->getModuleName())->toBe('Core');

    $plugin->boot(Panel::make('core-test'));
});

it('exposes cms plugin metadata and boots without error', function (): void {
    $plugin = new CMSPlugin();

    expect($plugin->getId())->toBe('cms')
        ->and($plugin->getModuleName())->toBe('CMS');

    $plugin->boot(Panel::make('cms-test'));
});

it('exposes erp plugin metadata and boots without error', function (): void {
    $plugin = new ERPPlugin();

    expect($plugin->getId())->toBe('erp')
        ->and($plugin->getModuleName())->toBe('ERP');

    $plugin->boot(Panel::make('erp-test'));
});
