<?php

declare(strict_types=1);

/**
 * Namespace-local function overrides for testing.
 */

namespace Modules\Core\Tests\Fixtures;

use Modules\Core\Console\HandleTestContext;

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
    if ($module !== 'Core' && HandleTestContext::$module_base !== '') {
        return HandleTestContext::$module_base . '/' . ltrim($path, '/');
    }

    return \module_path($module, $path);
}
