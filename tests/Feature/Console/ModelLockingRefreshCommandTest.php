<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Core\Locking\Console\ModelLockingRefreshCommand;
use Modules\Core\Tests\Stubs\Console\ConstructorConfiguredLockingModel;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;

it('lock refresh command merges application quiet option without duplicate definition', function (): void {
    $command = app(ModelLockingRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();

    expect($command->getDefinition()->hasOption('quiet'))->toBeTrue();
});

it('inspects schema through the constructor-configured model connection', function (): void {
    config()->set('database.connections.locking_refresh_constructor_connection', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    DB::purge('locking_refresh_constructor_connection');
    $connection = DB::connection('locking_refresh_constructor_connection');
    $connection->getSchemaBuilder()->create('locking_refresh_constructor_table', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
    });
    $connection->enableQueryLog();

    $command = app(ModelLockingRefreshCommand::class);
    $method = new ReflectionMethod($command, 'checkModel');
    $method->setAccessible(true);

    try {
        $method->invoke($command, ConstructorConfiguredLockingModel::class);

        expect(json_encode($connection->getQueryLog(), JSON_THROW_ON_ERROR))
            ->toContain('locking_refresh_constructor_table');
    } finally {
        DB::disconnect('locking_refresh_constructor_connection');
        DB::purge('locking_refresh_constructor_connection');
    }
});
