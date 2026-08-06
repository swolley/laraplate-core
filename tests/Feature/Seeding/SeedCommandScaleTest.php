<?php

declare(strict_types=1);

use Database\Seeders\DevDatabaseSeeder;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Console\SeedCommand;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Overrides\Seeder;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @param  array<string, bool>  $options
 */
function seedCommandWithInput(array $options): SeedCommand
{
    $command = app(SeedCommand::class);
    $command->setLaravel(app());
    $input = new ArrayInput($options, $command->getDefinition());

    (function () use ($input): void {
        $this->input = $input;
    })->call($command);

    return $command;
}

function resolveFactor(SeedCommand $command): float
{
    $method = new ReflectionMethod(SeedCommand::class, 'resolveDevSeedScale');

    return $method->invoke($command);
}

it('resolves --micro to a hundredth-scale factor', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--micro' => true])))->toBe(0.01);
});

it('resolves --min to a tenth-scale factor', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--min' => true])))->toBe(0.1);
});

it('resolves --mid to a half-scale factor', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--mid' => true])))->toBe(0.5);
});

it('resolves --max to a full-scale factor', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--max' => true])))->toBe(1.0);
});

it('defaults to micro scale when no flag is set', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true])))->toBe(0.01);
});

it('lets --micro win over every other flag', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--micro' => true, '--min' => true, '--mid' => true, '--max' => true])))->toBe(0.01);
});

it('lets --min win over --mid and --max', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--min' => true, '--mid' => true, '--max' => true])))->toBe(0.1);
});

it('lets --mid win over --max', function (): void {
    expect(resolveFactor(seedCommandWithInput(['--dev' => true, '--mid' => true, '--max' => true])))->toBe(0.5);
});

it('publishes the resolved scale to the container so dev seeders read it past the shared-command mutation', function (): void {
    app()->bind(DevDatabaseSeeder::class, fn () => new class(app(DatabaseManager::class)) extends Seeder
    {
        public function __destruct() {}

        public function run(): void
        {
            cache()->put(
                'captured_dev_seed_scale',
                app()->bound(BatchSeeder::SCALE_CONTAINER_KEY) ? app(BatchSeeder::SCALE_CONTAINER_KEY) : null,
            );
        }
    });

    $this->artisan('db:seed', ['--dev' => true, '--min' => true])->assertExitCode(0);

    expect(cache('captured_dev_seed_scale'))->toBe(0.1);
});

it('leaves the scale unpublished after a dev run completes', function (): void {
    app()->bind(DevDatabaseSeeder::class, fn () => new class(app(DatabaseManager::class)) extends Seeder
    {
        public function __destruct() {}

        public function run(): void {}
    });

    $this->artisan('db:seed', ['--dev' => true, '--mid' => true])->assertExitCode(0);

    expect(app()->bound(BatchSeeder::SCALE_CONTAINER_KEY))->toBeFalse();
});
