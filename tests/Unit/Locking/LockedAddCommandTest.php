<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Modules\Core\Locking\Console\LockedAddCommand;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Locking\LockedAddCommandTestDouble;
use Symfony\Component\Console\Command\Command as BaseCommand;

uses(LaravelTestCase::class);

function locking_stub_path(): string
{
    return dirname(__DIR__, 3) . '/app/Locking/Stubs/add_locked_column_to_table.stub';
}

it('locked add command returns invalid when model class does not exist', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $command = new LockedAddCommandTestDouble($files);
    $command->argModel = 'DefinitelyMissingModel';
    $command->argNamespace = 'App\\Models';

    $result = $command->handle();

    expect($result)->toBe(BaseCommand::INVALID)
        ->and($command->errors)->not->toBeEmpty();
});

it('locked add command handles namespaced model input branch', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $command = new LockedAddCommandTestDouble($files);
    $command->argModel = 'Foo\\Bar\\MissingModel';
    $command->argNamespace = null;

    $result = $command->handle();

    expect($result)->toBe(BaseCommand::INVALID)
        ->and($command->errors)->not->toBeEmpty();
});

it('locked add command handles existing model and keeps file when migration exists', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->once()->andReturnTrue();
    $files->shouldReceive('put')->never();

    $command = new LockedAddCommandTestDouble($files);
    $command->argModel = 'Setting';
    $command->argNamespace = 'Modules\\Core\\Models';
    $command->stubPath = locking_stub_path();

    $result = $command->handle();

    expect($result)->toBe(BaseCommand::SUCCESS)
        ->and($command->infos)->not->toBeEmpty();
});

it('locked add command writes migration file when target does not exist', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->once()->andReturnFalse();
    $files->shouldReceive('put')->once()->andReturnTrue();

    $command = new LockedAddCommandTestDouble($files);
    $command->argModel = 'Setting';
    $command->argNamespace = 'Modules\\Core\\Models';
    $command->stubPath = locking_stub_path();

    expect($command->handle())->toBe(BaseCommand::SUCCESS);
});

it('locked add command helper methods build migration path and stub contents', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $command = new LockedAddCommandTestDouble($files);
    $command->stubPath = locking_stub_path();

    $model = new Modules\Core\Models\Setting;
    $path = $command->generateMigrationPath($model);
    $stub = $command->getStubPath();
    $contents = $command->getStubContents($stub, ['ModelTable' => 'settings']);

    expect($path)->toContain('_add_locked_columns_to_')
        ->and($path)->toContain('settings.php')
        ->and($contents)->toContain('$ModelTable');
});

it('locked add command default stub path includes locking stubs folder', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $command = new LockedAddCommandTestDouble($files);

    expect($command->getStubPath())->toContain('Locking/Stubs');
});
