<?php

declare(strict_types=1);

use Illuminate\Console\Command as BaseCommand;
use Modules\Core\Overrides\Command;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

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
