<?php

declare(strict_types=1);

/**
 * Namespace-local function overrides for testing.
 */

namespace Modules\Core\Tests\Fixtures;

final class HandleTestContext
{
    /**
     * When true, namespace-local models() delegates to global helpers\models() (HelpersCache).
     */
    public static bool $models_from_global_helpers = false;

    /** @var list<class-string> */
    public static array $models = [];

    public static bool $uses_trait = false;

    public static string $app_base = '';

    public static string $db_base = '';

    public static string $module_base = '';

    /** @var array<string, mixed> */
    public static array $config = [];
}
