<?php

declare(strict_types=1);

namespace Modules\Core\Observers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Modules\Core\Models\User;

final class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function creating(User $user): void
    {
        $user->username ??= $user->email;

        $user->lang ??= App::getLocale();

        $user->password ??= Str::password();
    }

    public function created(User $user): void
    {
        if (! $user->hasVerifiedEmail() && config('core.auth.email_verification.enabled')) {
            $user->sendEmailVerificationNotification();
        }
    }

    public function deleted(User $user): void
    {
        if (config('core.auth.licenses.enabled') && $user->license_id) {
            $user->license_id = null;
            $user->save();
        }
    }
}
