<?php

declare(strict_types=1);

namespace Modules\Core\Auth;

use App\Models\User;
use Modules\Core\Auth\Concerns\ValidatesUserAccount;

/**
 * The account checks a password login runs in `FortifyCredentialsProvider`, applied to a passkey login
 * before the session starts: a verified passkey proves who the person is, not that the account may log in.
 */
final class PasskeyLoginAuthorizer
{
    use ValidatesUserAccount;

    /**
     * @return string|null the reason the login is refused, null when it is allowed
     */
    public function error(User $user): ?string
    {
        if (! config('core.auth.passkeys.enabled')) {
            return 'Passkey login is disabled';
        }

        $error = $this->accountValidityError($user);

        if ($error !== null) {
            return $error;
        }

        if (! $user->roles()->exists()) {
            return 'User not allowed to login';
        }

        if ($this->shouldVerifyEmail($user)) {
            return 'Email not verified';
        }

        $error = $this->checkLicense($user);

        return in_array($error, [null, '', '0'], true) ? null : $error;
    }
}
