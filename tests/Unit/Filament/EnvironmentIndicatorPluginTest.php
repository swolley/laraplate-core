<?php

declare(strict_types=1);

use Modules\Core\Filament\Plugins\EnvironmentIndicatorPlugin;
use Modules\Core\Models\User;

it('is visible only for super-admin users', function (): void {
    $plugin = new EnvironmentIndicatorPlugin;

    expect($plugin->userCanSee(null))->toBeFalse();

    $guest = Mockery::mock(User::class);
    $guest->shouldReceive('isSuperAdmin')->once()->andReturn(false);
    expect($plugin->userCanSee($guest))->toBeFalse();

    $super = Mockery::mock(User::class);
    $super->shouldReceive('isSuperAdmin')->once()->andReturn(true);
    expect($plugin->userCanSee($super))->toBeTrue();
});

it('shows debug warning only when production and debug are both true', function (): void {
    $plugin = new EnvironmentIndicatorPlugin;

    expect($plugin->shouldShowDebugWarning(false, true))->toBeFalse()
        ->and($plugin->shouldShowDebugWarning(true, false))->toBeFalse()
        ->and($plugin->shouldShowDebugWarning(true, true))->toBeTrue();
});
