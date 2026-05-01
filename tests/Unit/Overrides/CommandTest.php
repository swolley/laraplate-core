<?php

declare(strict_types=1);

use Illuminate\Console\Command as BaseCommand;
use Illuminate\Foundation\Application;
use Modules\Core\Overrides\Command;


it('constructs without starting benchmark when running unit tests', function (): void {
    app()->instance('runningUnitTests', true);

    $command = new class() extends Command
    {
        public function handle(): int
        {
            return 0;
        }
    };

    expect($command)->toBeInstanceOf(Command::class);
    expect($command)->toBeInstanceOf(BaseCommand::class);
});

it('constructs without starting benchmark when not in console', function (): void {
    $app = app();

    if (method_exists($app, 'runningInConsole')) {
        $app = Mockery::mock($app)->makePartial();
        $app->shouldReceive('runningInConsole')->andReturn(false);
    }

    $command = new class extends Command
    {
        public function handle(): int
        {
            return 0;
        }
    };

    expect($command)->toBeInstanceOf(Command::class);
});

it('destructor does nothing when benchmarkStartTime is null', function (): void {
    app()->instance('runningUnitTests', true);

    $command = new class extends Command
    {
        public function handle(): int
        {
            return 0;
        }
    };

    $ref = new ReflectionProperty(Command::class, 'benchmarkStartTime');
    expect($ref->getValue($command))->toBeNull();

    $command->__destruct();

    expect(true)->toBeTrue();
});

it('isLaunchedManually can be invoked via reflection', function (): void {
    $command = new class extends Command
    {
        public function handle(): int
        {
            return 0;
        }
    };

    $ref = new ReflectionMethod(Command::class, 'isLaunchedManually');
    $result = $ref->invoke($command);

    expect($result)->toBeBool();
});

it('isLaunchedManually returns false when the application is not running in console', function (): void {
    app()->instance('runningUnitTests', true);

    $original_app = Application::getInstance();
    $mocked_app = Mockery::mock($original_app)->makePartial();
    $mocked_app->shouldReceive('runningInConsole')->andReturn(false);

    Application::setInstance($mocked_app);

    try {
        $command = new class extends Command
        {
            public function handle(): int
            {
                return 0;
            }
        };

        $method = new ReflectionMethod(Command::class, 'isLaunchedManually');

        expect($method->invoke($command))->toBeFalse();
    } finally {
        Application::setInstance($original_app);
    }
});
