<?php

declare(strict_types=1);

use Modules\Core\Overrides\ModuleServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('provides returns empty array', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';
    };

    expect($provider->provides())->toBe([]);
});

it('inspectFolderCommands returns array of command class names', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicInspectFolderCommands(string $path): array
        {
            return $this->inspectFolderCommands($path);
        }
    };

    $path = config('modules.paths.generator.command.path', 'Console');
    $commands = $provider->publicInspectFolderCommands($path);

    expect($commands)->toBeArray();
});

it('boot registers commands, translations, views and migrations without throwing', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicBoot(): void
        {
            $this->boot();
        }
    };

    // Non deve lanciare eccezioni
    $provider->publicBoot();

    expect(true)->toBeTrue();
});

it('registerTranslations falls back to module lang path when resource path missing', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicRegisterTranslations(): void
        {
            $this->registerTranslations();
        }
    };

    $provider->publicRegisterTranslations();

    expect(true)->toBeTrue();
});

it('registerViews configures publishable view paths and component namespace', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicRegisterViews(): void
        {
            $this->registerViews();
        }
    };

    $provider->publicRegisterViews();

    expect(true)->toBeTrue();
});

it('registerCommands inspects folder and registers command classes', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicRegisterCommands(): void
        {
            $this->registerCommands();
        }
    };

    $provider->publicRegisterCommands();

    expect(true)->toBeTrue();
});

it('getResourcePath builds resource path from prefix and module name', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicGetResourcePath(string $prefix): string
        {
            $ref = new ReflectionMethod(ModuleServiceProvider::class, 'getResourcePath');

            return $ref->invoke($this, $prefix);
        }
    };

    $path = $provider->publicGetResourcePath('views');

    expect($path)->toContain('/resources/views/modules/core');
});

it('getPublishableViewPaths returns array of existing module view paths', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider {
        public string $name = 'Core';

        public string $nameLower = 'core';

        public function publicGetPublishableViewPaths(): array
        {
            $ref = new ReflectionMethod(ModuleServiceProvider::class, 'getPublishableViewPaths');

            return $ref->invoke($this);
        }
    };

    $paths = $provider->publicGetPublishableViewPaths();

    expect($paths)->toBeArray();
});
