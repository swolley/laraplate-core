<?php

declare(strict_types=1);

use Modules\Core\Filament\Services\ModuleVersionCatalog;

it('lists App first then installed modules alphabetically with versions and enabled flags', function (): void {
    $root = sys_get_temp_dir().'/module-version-catalog-'.uniqid('', true);
    $modules = $root.'/Modules';
    mkdir($modules.'/Zebra', 0777, true);
    mkdir($modules.'/Alpha', 0777, true);

    file_put_contents($root.'/composer.json', json_encode(['version' => 'v9.9.9'], JSON_THROW_ON_ERROR));
    file_put_contents($modules.'/Zebra/composer.json', json_encode(['version' => 'v1.0.0'], JSON_THROW_ON_ERROR));
    file_put_contents($modules.'/Alpha/composer.json', json_encode(['version' => 'v2.0.0'], JSON_THROW_ON_ERROR));

    $catalog = new ModuleVersionCatalog(
        app_composer_path: $root.'/composer.json',
        modules_path: $modules,
        is_module_enabled: static fn (string $name): bool => $name === 'Alpha',
    );

    $entries = $catalog->entries();

    expect($entries)->toHaveCount(3)
        ->and($entries[0]->name)->toBe('App')
        ->and($entries[0]->version)->toBe('v9.9.9')
        ->and($entries[0]->enabled)->toBeTrue()
        ->and($entries[0]->isApp)->toBeTrue()
        ->and($entries[1]->name)->toBe('Alpha')
        ->and($entries[1]->version)->toBe('v2.0.0')
        ->and($entries[1]->enabled)->toBeTrue()
        ->and($entries[2]->name)->toBe('Zebra')
        ->and($entries[2]->version)->toBe('v1.0.0')
        ->and($entries[2]->enabled)->toBeFalse();
});

it('returns unknown when composer version is missing', function (): void {
    $root = sys_get_temp_dir().'/module-version-catalog-'.uniqid('', true);
    $modules = $root.'/Modules';
    mkdir($modules.'/Bare', 0777, true);

    file_put_contents($root.'/composer.json', json_encode(['name' => 'app/root'], JSON_THROW_ON_ERROR));
    file_put_contents($modules.'/Bare/composer.json', json_encode(['name' => 'mod/bare'], JSON_THROW_ON_ERROR));

    $catalog = new ModuleVersionCatalog(
        app_composer_path: $root.'/composer.json',
        modules_path: $modules,
        is_module_enabled: static fn (string $name): bool => true,
    );

    $entries = $catalog->entries();

    expect($entries[0]->version)->toBe('unknown')
        ->and($entries[1]->version)->toBe('unknown');
});
