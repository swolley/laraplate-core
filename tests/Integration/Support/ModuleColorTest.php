<?php

declare(strict_types=1);

use Filament\Support\Colors\Color;
use Modules\Core\Support\ModuleColor;

it('reads the terminal colour declared in module.json', function (): void {
    expect(ModuleColor::terminal('Core'))->toBe('green');
});

it('translates the terminal colour into a Filament palette', function (): void {
    expect(ModuleColor::filament('Core'))->toBe(Color::Green);
});

it('returns null for a module that does not exist', function (): void {
    expect(ModuleColor::terminal('NotAModule'))->toBeNull()
        ->and(ModuleColor::filament('NotAModule'))->toBeNull();
});
