<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeding;

use Illuminate\Database\Seeder;

/**
 * Test double for SeedOrchestratorTest.
 *
 * Records execution via a static counter so a test can assert whether a
 * resumed run skipped it (it must, since it already succeeded).
 */
final class PassingStubSeeder extends Seeder
{
    public static int $runCount = 0;

    public function run(): void
    {
        self::$runCount++;
    }
}
