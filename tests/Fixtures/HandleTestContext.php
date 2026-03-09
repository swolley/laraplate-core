<?php

declare(strict_types=1);

/**
 * Namespace-local function overrides for testing.
 */

namespace Modules\Core\Tests\Fixtures;

final class HandleTestContext
{
    /** @var list<class-string> */
    public static array $models = [];

    public static bool $uses_trait = false;

    public static string $app_base = '';

    public static string $db_base = '';

    public static string $module_base = '';

    /** @var array<string, mixed> */
    public static array $config = [];
}
