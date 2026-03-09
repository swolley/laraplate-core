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
