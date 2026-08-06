<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Console\ModuleSeedCommand;
use Modules\Core\Database\Seeders\DevCoreDatabaseSeeder;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Overrides\Seeder;

it('derives the module development seeder class from the module name', function (): void {
    $command = app(ModuleSeedCommand::class);
    $command->setLaravel(app());

    $method = new ReflectionMethod(ModuleSeedCommand::class, 'devSeederClass');

    expect($method->invoke($command, 'Core'))->toBe(DevCoreDatabaseSeeder::class);
});

it('runs the module dev seeder and publishes the scale under --dev', function (): void {
    app()->bind(DevCoreDatabaseSeeder::class, fn () => new class(app(DatabaseManager::class)) extends Seeder
    {
        public function __destruct() {}

        public function run(): void
        {
            cache()->put(
                'module_seed_captured_scale',
                app()->bound(BatchSeeder::SCALE_CONTAINER_KEY) ? app(BatchSeeder::SCALE_CONTAINER_KEY) : null,
            );
        }
    });

    $this->artisan('module:seed', ['module' => ['Core'], '--dev' => true, '--mid' => true])
        ->assertExitCode(0);

    expect(cache('module_seed_captured_scale'))->toBe(0.5);
});

it('leaves the scale unpublished after a module dev run', function (): void {
    app()->bind(DevCoreDatabaseSeeder::class, fn () => new class(app(DatabaseManager::class)) extends Seeder
    {
        public function __destruct() {}

        public function run(): void {}
    });

    $this->artisan('module:seed', ['module' => ['Core'], '--dev' => true, '--min' => true])
        ->assertExitCode(0);

    expect(app()->bound(BatchSeeder::SCALE_CONTAINER_KEY))->toBeFalse();
});
