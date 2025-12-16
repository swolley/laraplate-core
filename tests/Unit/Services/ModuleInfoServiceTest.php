<?php

declare(strict_types=1);

use Modules\Core\Services\Docs\ModuleInfoService;
use Tests\TestCase;

uses(TestCase::class);

it('groups modules with models and controllers', function (): void {
    $service = new ModuleInfoService(
        modulesProvider: fn () => ['App', 'Cms'],
        modelsProvider: fn () => ['App\\Models\\User', 'Modules\\Cms\\Models\\Post'],
        controllersProvider: fn () => ['App\\Http\\Controllers\\HomeController', 'Modules\\Cms\\Http\\Controllers\\PostController'],
        composerReader: fn (string $path) => json_encode([
            'authors' => [['name' => 'Alice', 'email' => 'a@example.com']],
            'description' => 'Desc',
            'version' => '1.0.0',
        ]),
        moduleEnabled: fn (string $module) => $module === 'App',
    );

    $grouped = $service->groupedModules();

    expect($grouped)->toHaveKey('App');
    expect($grouped)->toHaveKey('Cms');
    expect($grouped['App']['models'])->toBe(['App\\Models\\User']);
    expect($grouped['Cms']['models'])->toBe(['Modules\\Cms\\Models\\Post']);
    expect($grouped['App']['controllers'])->toBe(['App\\Http\\Controllers\\HomeController']);
    expect($grouped['Cms']['controllers'])->toBe(['Modules\\Cms\\Http\\Controllers\\PostController']);
    expect($grouped['App']['isEnabled'])->toBeTrue();
    expect($grouped['Cms']['isEnabled'])->toBeFalse();
    expect($grouped['App']['version'])->not->toBeEmpty();
});
