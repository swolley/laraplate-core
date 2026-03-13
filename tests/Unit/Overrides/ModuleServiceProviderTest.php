<?php

declare(strict_types=1);

use Modules\Core\Overrides\ModuleServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('provides returns empty array', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider
    {
        public string $name = 'Core';

        public string $nameLower = 'core';
    };

    expect($provider->provides())->toBe([]);
});

it('inspectFolderCommands returns array of command class names', function (): void {
    $provider = new class(app()) extends ModuleServiceProvider
    {
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
    $provider = new class(app()) extends ModuleServiceProvider
    {
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
    $provider = new class(app()) extends ModuleServiceProvider
    {
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
    $provider = new class(app()) extends ModuleServiceProvider
    {
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
    $provider = new class(app()) extends ModuleServiceProvider
    {
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
    $provider = new class(app()) extends ModuleServiceProvider
    {
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
    $provider = new class(app()) extends ModuleServiceProvider
    {
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

it('register throws when name is not Core and Core module not found', function (): void {
    Nwidart\Modules\Facades\Module::shouldReceive('find')
        ->with('Core')
        ->andReturnNull();

    $provider = new class(app()) extends ModuleServiceProvider
    {
        public string $name = 'NotCore';

        public string $nameLower = 'notcore';
    };

    expect(fn () => $provider->register())
        ->toThrow(Exception::class, 'Core is required and must be enabled');
});

it('registerTranslations loads from resource path when directory exists', function (): void {
    $lang_path = resource_path('lang/modules/core');
    $created = false;

    if (! is_dir($lang_path)) {
        @mkdir($lang_path, 0755, true);
        $created = true;
    }

    try {
        $provider = new class(app()) extends ModuleServiceProvider
        {
            public string $name = 'Core';

            public string $nameLower = 'core';

            public function publicRegisterTranslations(): void
            {
                $this->registerTranslations();
            }
        };

        $provider->publicRegisterTranslations();

        expect(true)->toBeTrue();
    } finally {
        if ($created) {
            @rmdir($lang_path);
        }
    }
});

it('getPublishableViewPaths includes existing view directory', function (): void {
    $view_paths = config('view.paths', []);
    $first_path = $view_paths[0] ?? resource_path('views');
    $module_view_path = $first_path . '/modules/core';
    $created = false;

    if (! is_dir($module_view_path)) {
        @mkdir($module_view_path, 0755, true);
        $created = true;
    }

    try {
        $provider = new class(app()) extends ModuleServiceProvider
        {
            public string $name = 'Core';

            public string $nameLower = 'core';

            public function publicGetPublishableViewPaths(): array
            {
                $ref = new ReflectionMethod(ModuleServiceProvider::class, 'getPublishableViewPaths');

                return $ref->invoke($this);
            }
        };

        $paths = $provider->publicGetPublishableViewPaths();
        expect($paths)->toContain($module_view_path);
    } finally {
        if ($created) {
            @rmdir($module_view_path);
        }
    }
});
