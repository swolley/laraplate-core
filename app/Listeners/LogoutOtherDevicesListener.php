<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\User;

/**
 * With user licenses on, a login ends the user's sessions on every other device.
 *
 * A license is a seat, and a seat shared between browsers is two seats. Laravel ends the other
 * sessions by re-hashing the password, which needs the password in clear: the {@see Login} event
 * does not carry it, so it is taken from the {@see Attempting} event of the same request and
 * dropped as soon as the login it belongs to has been handled.
 *
 * Logins without a password (social login, impersonation, remember-me cookie) never pass through
 * {@see Attempting}, so they leave the other sessions alone. Neither does a super admin, who holds
 * no license seat and is exempt from the license checks everywhere else.
 *
 * Bound as scoped: the password must not outlive the request that submitted it.
 */
final class LogoutOtherDevicesListener
{
    private ?string $attempted_guard = null;

    private ?string $attempted_password = null;

    public function handleAttempting(Attempting $event): void
    {
        $password = $event->credentials['password'] ?? null;

        $this->attempted_guard = $event->guard;
        $this->attempted_password = is_string($password) ? $password : null;
    }

    public function handleLogin(Login $event): void
    {
        $password = $this->attempted_guard === $event->guard ? $this->attempted_password : null;

        $this->attempted_guard = null;
        $this->attempted_password = null;

        if ($password === null || ! config('auth.enable_user_licenses')) {
            return;
        }

        if ($event->user instanceof User && $event->user->isSuperAdmin()) {
            return;
        }

        $guard = Auth::guard($event->guard);

        if ($guard instanceof SessionGuard) {
            $guard->logoutOtherDevices($password);
        }
    }
}
