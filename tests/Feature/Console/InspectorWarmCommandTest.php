<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Console\InspectorWarmCommand;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Command\Command as BaseCommand;

uses(LaravelTestCase::class);

function registerInspectorWarmCommand(): void
{
    /** @var ConsoleKernel $kernel */
    $kernel = app(ConsoleKernel::class);

    // Avoid duplicate registration if already present
    foreach ($kernel->all() as $name => $command) {
        if ($name === 'inspector:warm') {
            return;
        }
    }

    $kernel->registerCommand(app(InspectorWarmCommand::class));
}

it('returns success and prints message when no tables to warm', function (): void {
    registerInspectorWarmCommand();
    Schema::shouldReceive('connection')
        ->andReturnSelf();

    Schema::shouldReceive('getTables')
        ->andReturn([]);
    Schema::shouldReceive('dropIfExists')
        ->zeroOrMoreTimes();
    Schema::shouldReceive('table')
        ->zeroOrMoreTimes();
    Schema::shouldReceive('drop')
        ->zeroOrMoreTimes();

    $tester = $this->artisan('inspector:warm');

    $tester->assertExitCode(BaseCommand::SUCCESS);
});

it('warms inspector cache for discovered tables', function (): void {
    registerInspectorWarmCommand();
    Schema::shouldReceive('connection')
        ->andReturnSelf();

    Schema::shouldReceive('getTables')
        ->andReturn([
            ['name' => 'users'],
            ['name' => 'posts'],
        ]);
    Schema::shouldReceive('dropIfExists')
        ->zeroOrMoreTimes();
    Schema::shouldReceive('table')
        ->zeroOrMoreTimes();
    Schema::shouldReceive('drop')
        ->zeroOrMoreTimes();

    $tester = $this->artisan('inspector:warm');

    $tester->assertExitCode(BaseCommand::SUCCESS);
});
