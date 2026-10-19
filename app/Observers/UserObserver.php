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
        // Deleted for good: the row is gone, and saving the model now would insert it again. Its tokens and
        // notifications hang from it by a polymorphic key and go with it; the license is freed with the row.
        if (! $user->exists) {
            $user->tokens()->delete();
            $user->notifications()->delete();

            return;
        }

        if (config('core.auth.licenses.enabled') && $user->license_id) {
            $user->license_id = null;
            $user->save();
        }
    }
}
