<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        self::bootstrapMinimalTestEnvironment();
        parent::setUp();
    }

    /**
     * Registers config(), facades, module_path(), fake(), and fake fixture autoloading for lightweight tests.
     */
    private static function bootstrapMinimalTestEnvironment(): void
    {
        require_once __DIR__ . '/minimal-test-environment.php';
    }
}
