<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Overrides\Seeder;

uses(Tests\TestCase::class);

afterEach(function (): void {
    putenv(Seeder::PARALLEL_BATCH_WORKER_ENV);
});

it('keeps the legacy no-argument bootstrap hook contract', function (): void {
    $bootstrap = new ReflectionMethod(BatchSeeder::class, 'bootstrapChildProcess');

    expect($bootstrap->getNumberOfRequiredParameters())->toBe(0);
});

it('bootstrapChildProcess clears benchmark state inherited after fork simulation', function (): void {
    config()->set('app.debug', true);

    $seeder = new class(app(DatabaseManager::class)) extends BatchSeeder
    {
        public function __destruct()
        {
            // Skip parent destructor: Pest tears down the app before object destruction,
            // and Seeder::__destruct() calls config() which is no longer available.
        }

        protected function execute(): void {}
    };

    $benchmark_prop = (new ReflectionObject($seeder))->getProperty('benchmarkStartTime');
    $benchmark_prop->setAccessible(true);

    expect($benchmark_prop->getValue($seeder))->not->toBeNull();

    $bootstrap = new ReflectionMethod(BatchSeeder::class, 'bootstrapChildProcess');
    $bootstrap->setAccessible(true);
    $connection_name = (new ReflectionClass(BatchSeeder::class))->getProperty('childDatabaseConnectionName');
    $connection_name->setValue($seeder, app(DatabaseManager::class)->getDefaultConnection());
    $bootstrap->invoke($seeder);

    expect($benchmark_prop->getValue($seeder))->toBeNull();
    expect(getenv(Seeder::PARALLEL_BATCH_WORKER_ENV))->toBe('1');
});

it('bootstrapChildProcess reconnects the resolved worker connection', function (): void {
    $seeder = new class(app(DatabaseManager::class)) extends BatchSeeder
    {
        public function __destruct() {}

        protected function execute(): void {}
    };

    $bootstrap = new ReflectionMethod(BatchSeeder::class, 'bootstrapChildProcess');
    $bootstrap->setAccessible(true);
    $connection_name = (new ReflectionClass(BatchSeeder::class))->getProperty('childDatabaseConnectionName');
    $connection_name->setValue($seeder, 'batch_affinity');
    $original = DB::getFacadeRoot();
    $database = Mockery::mock(DatabaseManager::class);
    $database->shouldReceive('reconnect')->once()->with('batch_affinity');
    DB::swap($database);

    try {
        $bootstrap->invoke($seeder);
    } finally {
        DB::swap($original);
    }
});
