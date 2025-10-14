<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Lab404\Impersonate\Impersonate;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use RuntimeException;

final class AfterLoginListener
{
    /**
     * @throws RuntimeException
     * @throws UnauthorizedException
     */
    public static function checkUserLicense(Authenticatable $user): void
    {
        if (config('auth.enable_user_licenses') && class_uses_trait($user, Impersonate::class) && $user instanceof User && (! $user->isGuest() && ! $user->isSuperadmin() && $user->license_id === null)) {
            $available_licenses = License::query()->whereDoesntHave('user')->get();

            throw_if($available_licenses->isEmpty(), UnauthorizedException::class, 'No licenses available');
            $user->license()->associate($available_licenses->first());
        }
    }

    /**
     * Handle the event.
     */
    public function handle(Login $login): void
    {
        /** @var Authenticatable&User&Impersonate $user */
        $user = $login->user;

        if (! class_uses_trait($user, Impersonate::class)) {
            self::checkUserLicense($user);
            $user->update(['last_login_at' => Date::now()]);

            if ($user->isUnlocked()) {
                Auth::logoutOtherDevices($user->password);
            }
            Log::info('{username} logged in', ['username' => $user->username]);
        } elseif ($user->isImpersonated()) {
            $impersonator = $user->getImpersonator();
            Log::info('{impersonator} is impersonating {impersonated}', ['impersonator' => $impersonator->username, 'impersonated' => $user->username]);
        }
    }
}
