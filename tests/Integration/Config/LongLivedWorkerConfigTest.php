<?php

declare(strict_types=1);

it('registers the Spatie permission reset listener for long-lived workers', function (): void {
    // Spatie's PermissionRegistrar keeps roles and permissions in memory. Without this
    // listener a shared worker never clears them between requests, which would undo the
    // request-scoped permission memoization. The flag is inert while Octane is absent,
    // because Spatie only binds the listener when Octane's events exist.
    expect(config('permission.register_octane_reset_listener'))->toBeTrue();
});
