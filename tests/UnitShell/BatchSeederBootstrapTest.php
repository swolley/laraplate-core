<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Overrides\Seeder;

uses(Tests\TestCase::class);

afterEach(function (): void {
    putenv(Seeder::PARALLEL_BATCH_WORKER_ENV);
});

it('bootstrapChildProcess clears benchmark state inherited after fork simulation', function (): void {
    config()->set('app.debug', true);

    $seeder = new class(app(DatabaseManager::class)) extends BatchSeeder
    {
        protected function execute(): void {}

        public function __destruct()
        {
            // Skip parent destructor: Pest tears down the app before object destruction,
            // and Seeder::__destruct() calls config() which is no longer available.
        }
    };

    $benchmark_prop = (new ReflectionObject($seeder))->getProperty('benchmarkStartTime');
    $benchmark_prop->setAccessible(true);

    expect($benchmark_prop->getValue($seeder))->not->toBeNull();

    $bootstrap = new ReflectionMethod(BatchSeeder::class, 'bootstrapChildProcess');
    $bootstrap->setAccessible(true);
    $bootstrap->invoke($seeder);

    expect($benchmark_prop->getValue($seeder))->toBeNull();
    expect(getenv(Seeder::PARALLEL_BATCH_WORKER_ENV))->toBe('1');
});
