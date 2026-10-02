<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Modules\Core\Console\CreateEntityCommand;
use Modules\Core\Exceptions\ConfigurationException;

/**
 * Module-free coverage of `model:create-entity`. Creating entities needs a module that
 * defines an Entity model, so that runs in the modules' own tests (CMS covers it).
 */
beforeEach(function (): void {
    static $command_registered = false;

    if (! $command_registered) {
        app(ConsoleKernel::class)->registerCommand(app(CreateEntityCommand::class));
        $command_registered = true;
    }
});

it('shows help without prompting', function (): void {
    $this->artisan('model:create-entity', ['--help' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('model:create-entity');
});

it('defines optional entity argument, module and content-model options', function (): void {
    $command = app(CreateEntityCommand::class);

    $arguments_method = new ReflectionMethod($command, 'getArguments');
    $arguments_method->setAccessible(true);
    $options_method = new ReflectionMethod($command, 'getOptions');
    $options_method->setAccessible(true);

    $arguments = $arguments_method->invoke($command);
    $options = $options_method->invoke($command);

    expect($arguments)->toHaveCount(1)
        ->and($arguments[0][0])->toBe('entity')
        ->and(array_column($options, 0))->toBe(['module', 'content-model'])
        ->and($command->getDefinition()->hasOption('module'))->toBeTrue();
});

it('refuses a module that defines no Entity model', function (): void {
    expect(fn () => $this->artisan('model:create-entity', ['entity' => 'Anything', '--module' => 'NoSuchModule'])->run())
        ->toThrow(ConfigurationException::class, 'Module NoSuchModule defines no Entity model.');
});
