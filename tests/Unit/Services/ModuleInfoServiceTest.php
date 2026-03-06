<?php

declare(strict_types=1);

use Modules\Core\Services\Docs\ModuleInfoService;
uses(Tests\LaravelTestCase::class);

it('groups modules with models and controllers', function (): void {
    $service = new ModuleInfoService(
        modulesProvider: static fn () => ['App', 'Core'],
        modelsProvider: static fn () => ['App\\Models\\User', 'Modules\\Core\\Models\\User'],
        controllersProvider: static fn () => ['App\\Http\\Controllers\\HomeController', 'Modules\\Core\\Http\\Controllers\\SettingController'],
        composerReader: static fn (string $path) => json_encode([
            'authors' => [['name' => 'Alice', 'email' => 'a@example.com']],
            'description' => 'Desc',
            'version' => '1.0.0',
        ]),
        moduleEnabled: static fn (string $module) => $module === 'App',
    );

    $grouped = $service->groupedModules();

    expect($grouped)->toHaveKey('App');
    expect($grouped)->toHaveKey('Core');
    expect($grouped['App']['models'])->toBe(['App\\Models\\User']);
    expect($grouped['Core']['models'])->toBe(['Modules\\Core\\Models\\User']);
    expect($grouped['App']['controllers'])->toBe(['App\\Http\\Controllers\\HomeController']);
    expect($grouped['Core']['controllers'])->toBe(['Modules\\Core\\Http\\Controllers\\SettingController']);
    expect($grouped['App']['isEnabled'])->toBeTrue();
    expect($grouped['Core']['isEnabled'])->toBeFalse();
    expect($grouped['App'])->toHaveKey('version');
    expect($grouped['Core']['version'])->not->toBeEmpty();
});
