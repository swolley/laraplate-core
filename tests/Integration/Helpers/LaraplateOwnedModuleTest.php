<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

it('treats App as never laraplate owned', function (): void {
    expect(is_laraplate_owned_module('App'))->toBeFalse()
        ->and(is_laraplate_owned_module('app'))->toBeFalse();
});

it('treats official modules with laraplate_owned true as owned', function (): void {
    expect(is_laraplate_owned_module('Core'))->toBeTrue()
        ->and(is_laraplate_owned_module('CMS'))->toBeTrue();
});

it('returns false for unknown modules', function (): void {
    expect(is_laraplate_owned_module('DefinitelyNotAModule'))->toBeFalse();
});

it('honours explicit laraplate_owned false over composer package name', function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laraplate-owned-'.uniqid('', true);
    mkdir($path);

    file_put_contents($path.DIRECTORY_SEPARATOR.'module.json', json_encode([
        'name' => 'Fake',
        'laraplate_owned' => false,
    ], JSON_THROW_ON_ERROR));

    file_put_contents($path.DIRECTORY_SEPARATOR.'composer.json', json_encode([
        'name' => 'swolley/laraplate-fake',
    ], JSON_THROW_ON_ERROR));

    try {
        expect(laraplate_module_is_owned_at_path($path))->toBeFalse();
    } finally {
        unlink($path.DIRECTORY_SEPARATOR.'module.json');
        unlink($path.DIRECTORY_SEPARATOR.'composer.json');
        rmdir($path);
    }
});

it('falls back to swolley/laraplate-* composer name when flag is absent', function (): void {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laraplate-owned-'.uniqid('', true);
    mkdir($path);

    file_put_contents($path.DIRECTORY_SEPARATOR.'module.json', json_encode([
        'name' => 'Fake',
    ], JSON_THROW_ON_ERROR));

    file_put_contents($path.DIRECTORY_SEPARATOR.'composer.json', json_encode([
        'name' => 'swolley/laraplate-fake',
    ], JSON_THROW_ON_ERROR));

    try {
        expect(laraplate_module_is_owned_at_path($path))->toBeTrue();
    } finally {
        unlink($path.DIRECTORY_SEPARATOR.'module.json');
        unlink($path.DIRECTORY_SEPARATOR.'composer.json');
        rmdir($path);
    }
});

it('exposes ownership via Module macro', function (): void {
    $module = Module::find('Core');

    expect($module)->not->toBeNull()
        ->and($module->isLaraplateOwned())->toBeTrue();
});

it('filters modules by name with laraplate ownership helper', function (): void {
    $custom = modules(
        onlyActive: false,
        filter: static fn (string $name): bool => ! is_laraplate_owned_module($name),
    );

    expect($custom)->not->toContain('Core')
        ->and($custom)->not->toContain('CMS');
});
