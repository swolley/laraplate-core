<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Concerns;

use App\Models\User;
use Modules\Core\Models\License;

trait ValidatesUserAccount
{
    private function accountValidityError(User $user): ?string
    {
        if (! $user->canAuthenticate()) {
            return 'Account is not active';
        }

        return null;
    }

    private function checkLicense(User $user): ?string
    {
        if (! config('auth.enable_user_licenses')) {
            return null;
        }

        if (! $user->license_id) {
            $available_license = License::query()
                ->doesntHave('user')
                ->first();

            if (
                ! $available_license
                && $user->roles->where('name', 'superadmin')->isEmpty()
            ) {
                return 'No free licenses available';
            }
        }

        return null;
    }
}
