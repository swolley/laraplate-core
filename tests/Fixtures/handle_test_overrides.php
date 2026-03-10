<?php

declare(strict_types=1);

/**
 * Namespace-local function overrides for testing the MakeModelTranslatableCommand.
 *
 * These overrides live in the same namespace as the command so that unqualified
 * calls in production code resolve here during tests, without changing the
 * application logic.
 */

namespace Modules\Core\Console;

use Modules\Core\Tests\Fixtures\HandleTestContext;

function models(bool $onlyActive = true): array
{
    return HandleTestContext::$models;
}

function class_uses_trait(string|object $class, string $uses, bool $recursive = true): bool
{
    return HandleTestContext::$uses_trait;
}

function app_path(string $path = ''): string
{
    return HandleTestContext::$app_base . '/' . ltrim($path, '/');
}

function database_path(string $path = ''): string
{
    return HandleTestContext::$db_base . '/' . ltrim($path, '/');
}

function config(?string $key = null, mixed $default = null): mixed
{
    return HandleTestContext::$config[$key] ?? $default;
}

function module_path(string $module, string $path = ''): string
{
    $base = HandleTestContext::$module_base !== ''
        ? HandleTestContext::$module_base
        // Fallback for tests when a base is not explicitly provided
        : dirname(__DIR__, 3) . '/' . $module;

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}
