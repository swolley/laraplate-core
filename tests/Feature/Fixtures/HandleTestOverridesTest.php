<?php

declare(strict_types=1);

use Modules\Core\Tests\Fixtures\HandleTestContext;

use function Modules\Core\Console\config as namespaced_config;

require_once dirname(__DIR__, 2) . '/Fixtures/handle_test_overrides.php';

/**
 * The namespace-local overrides shadow global helpers for every class in
 * Modules\Core\Console once this fixture is loaded, for the whole process.
 * They must therefore stay inert unless a test explicitly configures them.
 */
afterEach(function (): void {
    HandleTestContext::$config = [];
});

it('returns the stubbed value when the key is configured', function (): void {
    HandleTestContext::$config['core.some.flag'] = 'stubbed';

    expect(namespaced_config('core.some.flag'))->toBe('stubbed');
});

it('keeps unstubbed keys sandboxed instead of resolving real config', function (): void {
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
