<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Full Laravel application without running migrations (faster, no DB refresh).
 */
abstract class ApplicationTestCase extends \Tests\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureCoolsamModulesResourceAlias();
    }

    /**
     * Skip migrate:fresh for tests that only need the container.
     */
    protected function refreshTestDatabase(): void
    {
    }

    private function ensureCoolsamModulesResourceAlias(): void
    {
        if (class_exists(\Coolsam\Modules\Resource::class)) {
            return;
        }

        if (! class_exists(\Filament\Resources\Resource::class)) {
            return;
        }

        class_alias(\Filament\Resources\Resource::class, \Coolsam\Modules\Resource::class);
    }
}
