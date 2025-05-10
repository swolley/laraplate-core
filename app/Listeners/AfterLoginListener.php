<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use RuntimeException;
use Modules\Core\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Models\License;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Impersonate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\UnauthorizedException;

final class AfterLoginListener
{
    /**
     * @param Authenticatable $user 
     * @return void 
     * @throws RuntimeException 
     * @throws UnauthorizedException 
     */
    public static function checkUserLicense(Authenticatable $user): void
    {
        if (config('auth.enable_user_licenses') && class_uses_trait($user, Impersonate::class) && $user instanceof User) {
            if (! $user->isGuest() && ! $user->isSuperadmin() && $user->license_id === null) {
                $available_licenses = License::query()->whereDoesntHave('user')->get();
                if ($available_licenses->isEmpty()) {
                    throw new UnauthorizedException('No licenses available');
                }
                $user->license()->associate($available_licenses->first());
            }
        }
    }

    /**
     * Handle the event.
     */
    public function handle(Login $login): void
    {
        /** @var Authenticatable&User&Impersonate $user */
        $user = $login->user;

        if (!class_uses_trait($user, Impersonate::class)) {
            self::checkUserLicense($user);
            $user->update(['last_login_at' => Carbon::now()]);

            if ($user->isUnlocked()) {
                Auth::logoutOtherDevices($user->password);
            }
            Log::info('{username} logged in', ['username' => $user->username]);
        } else {
            if ($user->isImpersonated()) {
                $impersonator = $user->getImpersonator();
                Log::info('{impersonator} is impersonating {impersonated}', ['impersonator' => $impersonator->username, 'impersonated' => $user->username]);
            }
        }
    }
}
