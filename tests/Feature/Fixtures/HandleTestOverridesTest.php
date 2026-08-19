<?php

declare(strict_types=1);

use function Modules\Core\Console\config as namespaced_config;

use Modules\Core\Tests\Fixtures\HandleTestContext;

require_once dirname(__DIR__, 2) . '/Fixtures/handle_test_overrides.php';

/**
 * The namespace-local overrides shadow global helpers for every class in
 * Modules\Core\Console once this fixture is loaded, for the whole process.
 * They must therefore stay inert unless a test explicitly configures them.
 */
afterEach(function (): void {
    HandleTestContext::$config = [];
    HandleTestContext::$config_from_global_helpers = true;
    HandleTestContext::$app_base = '';
    HandleTestContext::$db_base = '';
});

it('delegates to the real config helper by default so it does not shadow other console code', function (): void {
    // Default state: the override must not swallow config reads made by unrelated
    // Modules\Core\Console code after this fixture is loaded at file scope.
    config(['core.real.key' => 'real']);

    expect(namespaced_config('core.real.key'))->toBe('real');
});

it('returns the stubbed value when the key is configured', function (): void {
    HandleTestContext::$config_from_global_helpers = false;
    HandleTestContext::$config['core.some.flag'] = 'stubbed';

    expect(namespaced_config('core.some.flag'))->toBe('stubbed');
});

it('keeps unstubbed keys sandboxed instead of resolving real config', function (): void {
    HandleTestContext::$config_from_global_helpers = false;
    HandleTestContext::$config = [];
    config(['core.untouched.key' => 'real']);

    expect(namespaced_config('core.untouched.key'))->toBeNull();
});

it('supports the array setter form used by production commands', function (): void {
    HandleTestContext::$config = [];

    namespaced_config(['core.written.by.override' => 'written']);

    expect(config('core.written.by.override'))->toBe('written');
});

it('still honours an explicit default for an unknown key', function (): void {
    HandleTestContext::$config = [];

    expect(namespaced_config('core.definitely.missing', 'fallback'))->toBe('fallback');
});

it('keeps app_path and database_path inert when sandbox bases are empty', function (): void {
    HandleTestContext::$app_base = '';
    HandleTestContext::$db_base = '';

    expect(\Modules\Core\Console\app_path('Filament/Resources/Users/UserResource.php'))
        ->toBe(\app_path('Filament/Resources/Users/UserResource.php'))
        ->and(\Modules\Core\Console\database_path('migrations'))
        ->toBe(\database_path('migrations'));
});

it('sandboxes app_path when a base is configured', function (): void {
    HandleTestContext::$app_base = '/tmp/handle-test-app';

    expect(\Modules\Core\Console\app_path('Filament/Resources/Users/UserResource.php'))
        ->toBe('/tmp/handle-test-app/Filament/Resources/Users/UserResource.php');

    HandleTestContext::$app_base = '';
});
