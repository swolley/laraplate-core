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
    if (HandleTestContext::$models_from_global_helpers) {
        return \models($onlyActive);
    }

    return HandleTestContext::$models;
}

function class_uses_trait(string|object $class, string $uses, bool $recursive = true): bool
{
    if (HandleTestContext::$models_from_global_helpers) {
        return \class_uses_trait($class, $uses, $recursive);
    }

    return HandleTestContext::$uses_trait;
}

function app_path(string $path = ''): string
{
    // Stay inert unless a test configures a sandbox base — otherwise every
    // Modules\Core\Console command would resolve broken paths like `/Filament/...`
    // for the rest of the process after this fixture is loaded at file scope.
    if (HandleTestContext::$app_base === '') {
        return \app_path($path);
    }

    return HandleTestContext::$app_base . '/' . ltrim($path, '/');
}

function database_path(string $path = ''): string
{
    if (HandleTestContext::$db_base === '') {
        return \database_path($path);
    }

    return HandleTestContext::$db_base . '/' . ltrim($path, '/');
}

/**
 * Reads resolve against {@see HandleTestContext::$config}, deliberately returning
 * the default for unstubbed keys: commands under test rely on that to stay
 * sandboxed instead of resolving real module paths and writing into the repo.
 *
 * The array setter form carries no key to stub and cannot be answered from the
 * context, so it delegates to the real helper. Without this branch it raised a
 * TypeError in any command reached after this file was loaded — the file is
 * required at file scope and shadows `config()` for this whole namespace for the
 * rest of the process, so the crash surfaced purely by test ordering.
 *
 * @param  array<string, mixed>|string|null  $key
 */
function config(array|string|null $key = null, mixed $default = null): mixed
{
    if (is_array($key)) {
        return \config($key, $default);
    }

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
