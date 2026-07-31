<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

uses(Tests\TestCase::class);

/**
 * Architectural guard only. Seeder behaviour is covered by
 * Modules/Core/tests/Feature/Database/CoreDatabaseSeederTest.php.
 */
it('never opens seeder transactions on the default connection', function (): void {
    $source = file_get_contents((new ReflectionClass(CoreDatabaseSeeder::class))->getFileName());

    expect($source)->not->toContain('DB::transaction(');
});
