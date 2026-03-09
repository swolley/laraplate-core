<?php

declare(strict_types=1);

namespace Modules\Core\Console;

/**
 * Test-only context holder for MakeModelTranslatableCommand tests.
 *
 * This class mirrors the shape of the test fixture context so that
 * tests can reference it without requiring additional runtime wiring.
 */
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

