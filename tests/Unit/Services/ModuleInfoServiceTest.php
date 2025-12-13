<?php

declare(strict_types=1);

use Modules\Core\Services\Docs\ModuleInfoService;
use Tests\TestCase;

final class ModuleInfoServiceTest extends TestCase
{
    public function test_groups_modules_with_models_and_controllers(): void
    {
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

        $this->assertArrayHasKey('App', $grouped);
        $this->assertArrayHasKey('Cms', $grouped);
        $this->assertSame(['App\\Models\\User'], $grouped['App']['models']);
        $this->assertSame(['Modules\\Cms\\Models\\Post'], $grouped['Cms']['models']);
        $this->assertSame(['App\\Http\\Controllers\\HomeController'], $grouped['App']['controllers']);
        $this->assertSame(['Modules\\Cms\\Http\\Controllers\\PostController'], $grouped['Cms']['controllers']);
        $this->assertTrue($grouped['App']['isEnabled']);
        $this->assertFalse($grouped['Cms']['isEnabled']);
        $this->assertNotEmpty($grouped['App']['version']);
    }
}

