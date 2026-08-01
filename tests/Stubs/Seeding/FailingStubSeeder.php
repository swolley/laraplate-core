<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeding;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Setting;
use RuntimeException;

/**
 * Test double for SeedOrchestratorTest.
 *
 * Writes a marker row and then throws, so a test can assert that the
 * orchestrator's per-node transaction rolled the partial write back rather
 * than leaving it committed.
 */
final class FailingStubSeeder extends Seeder
{
    public const string PARTIAL_MARKER = 'seed-orchestrator-test-partial-marker';

    public function run(): void
    {
        Setting::factory()->persistedWithoutApprovalCapture()->create([
            'name' => self::PARTIAL_MARKER,
        ]);

        throw new RuntimeException('stub failure');
    }
}
