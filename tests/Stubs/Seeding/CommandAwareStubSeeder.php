<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeding;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

/**
 * Test double for SeedOrchestratorTest.
 *
 * Captures whatever $this->command the orchestrator wired in, so a test can
 * assert command propagation without depending on a real production seeder
 * (CoreDatabaseSeeder, CMSDatabaseSeeder, ...) that calls $this->command->line()
 * without a null-safe operator.
 */
final class CommandAwareStubSeeder extends Seeder
{
    public static ?Command $capturedCommand = null;

    public function run(): void
    {
        self::$capturedCommand = $this->command;
    }
}
