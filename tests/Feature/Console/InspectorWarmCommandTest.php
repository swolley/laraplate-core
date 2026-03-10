<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Core\Console\InspectorWarmCommand;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Command\Command as BaseCommand;

uses(LaravelTestCase::class);

it('returns success and prints message when no tables to warm', function (): void {
    Schema::shouldReceive('connection')
        ->once()
        ->with(null)
        ->andReturnSelf();

    Schema::shouldReceive('getTables')
        ->once()
        ->andReturn([]);

    $command = new InspectorWarmCommand();

    $tester = $this->artisan('inspector:warm');

    $tester->expectsOutput('No tables to warm.')
        ->assertExitCode(BaseCommand::SUCCESS);
});

it('warms inspector cache for discovered tables', function (): void {
    Schema::shouldReceive('connection')
        ->once()
        ->with(null)
        ->andReturnSelf();

    Schema::shouldReceive('getTables')
        ->once()
        ->andReturn([
            ['name' => 'users'],
            ['name' => 'posts'],
        ]);

    $command = new InspectorWarmCommand();

    $tester = $this->artisan('inspector:warm');

    $tester->expectsOutput('Warmed inspector cache for 2 table(s).')
        ->assertExitCode(BaseCommand::SUCCESS);
});
