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

    /**
     * @var list<class-string>
     */
    public static array $models = [];

    public static bool $uses_trait = false;

    /**
     * When true, the namespace-local config() delegates to the global config()
     * helper. It must default to true so that other Modules\Core\Console code
     * (e.g. commands unrelated to these tests) keeps reading the real config
     * once this fixture is loaded at file scope; the handle tests flip it off
     * to activate the stub map for the command under test.
     */
    public static bool $config_from_global_helpers = true;

    public static string $app_base = '';

    public static string $db_base = '';

    public static string $module_base = '';

    /**
     * @var array<string, mixed>
     */
    public static array $config = [];
}
