<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Helpers\BatchSeeder;
use Modules\Core\Models\License;

uses(Tests\TestCase::class);

function makeVersioningSeeder(): BatchSeeder
{
    return new class(app(DatabaseManager::class)) extends BatchSeeder
    {
        public function __destruct() {}

        protected function execute(): void {}
    };
}

function invokeVersioning(BatchSeeder $seeder, array $models, callable $callback): mixed
{
    $method = new ReflectionMethod(BatchSeeder::class, 'withoutModelVersioning');

    return $method->invoke($seeder, $models, $callback);
}

it('disables versioning for the given models during the callback and restores it after', function (): void {
    License::enableVersioning();

    $inside = null;
    invokeVersioning(makeVersioningSeeder(), [License::class], function () use (&$inside): void {
        $inside = License::getVersioning();
    });

    expect($inside)->toBeFalse()
        ->and(License::getVersioning())->toBeTrue();
});

it('restores versioning even when the callback throws', function (): void {
    License::enableVersioning();

    try {
        invokeVersioning(makeVersioningSeeder(), [License::class], function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(License::getVersioning())->toBeTrue();
});
