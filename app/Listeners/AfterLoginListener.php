<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Models\Impersonate;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use RuntimeException;

final class AfterLoginListener
{
    /**
     * @throws RuntimeException
     * @throws AuthorizationException
     */
    public static function checkUserLicense(Authenticatable $user): void
    {
        if (config('core.auth.licenses.enabled') && in_array(Impersonate::class, class_uses_recursive($user::class), true) && $user instanceof User && (! $user->isGuest() && ! $user->isSuperAdmin() && $user->license_id === null)) {
            $available_licenses = License::query()->whereDoesntHave('user')->first();

            throw_if(! $available_licenses, AuthorizationException::class, 'No licenses available');
            $user->license()->associate($available_licenses);
        }
    }

    /**
     * Handle the event.
     *
     * The bookkeeping used to sit behind a check for whether the user class supports impersonation,
     * which every real user does, so it never ran. What tells an ordinary login apart is whether the
     * session is an impersonation, not whether it could be.
     *
     * Ending the user's other sessions is {@see LogoutOtherDevicesListener}'s job: it needs the
     * password in clear, which this event does not carry.
     */
    public function handle(Login $login): void
    {
        /** @var Authenticatable&User&Impersonate $user */
        $user = $login->user;

        if (in_array(Impersonate::class, class_uses_recursive($user::class), true) && $user->isImpersonated()) {
            $impersonator = $user->getImpersonator();
            Log::info('{impersonator} is impersonating {impersonated}', ['impersonator' => $impersonator->username, 'impersonated' => $user->username]);

            return;
        }

        self::checkUserLicense($user);

        // Recording the login and handing out a seat are the system's writes, not an edit of the
        // account: a lock on the user must not refuse them.
        Locked::withoutGuard(fn (): bool => $user->forceFill(['last_login_at' => Date::now()])->save());

        Log::info('{username} logged in', ['username' => $user->username]);
    }
}
