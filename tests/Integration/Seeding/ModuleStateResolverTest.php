<?php

declare(strict_types=1);

use Modules\Core\Seeding\ModuleState;
use Modules\Core\Seeding\ModuleStateResolver;

/**
 * nwidart/laravel-modules keys `Module::all()`/`allEnabled()` by
 * `strtolower($name)` ({@see \Nwidart\Modules\FileRepository::scan()}), while
 * `core_settings.module` stores the module's declared case (e.g. `Core`,
 * `MES`). A resolver that compares the raw name against those arrays with
 * `array_key_exists()` would never match a real module and would misclassify
 * every enabled module as absent. These tests lock in that the resolver is
 * case-insensitive against the module's declared name.
 */
it('resolves an enabled module regardless of the casing supplied', function (string $module): void {
    expect(app(ModuleStateResolver::class)->for($module))->toBe(ModuleState::Enabled);
})->with([
    'declared case' => ['Core'],
    'lowercase' => ['core'],
    'uppercase' => ['CORE'],
]);

it('resolves a module absent from disk regardless of casing', function (): void {
    expect(app(ModuleStateResolver::class)->for('NotARealModule'))->toBe(ModuleState::Absent);
});

it('treats a null or empty module as enabled', function (?string $module): void {
    expect(app(ModuleStateResolver::class)->for($module))->toBe(ModuleState::Enabled);
})->with([
    'null' => [null],
    'empty string' => [''],
]);
