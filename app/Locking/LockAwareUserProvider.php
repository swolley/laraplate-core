<?php

declare(strict_types=1);

namespace Modules\Core\Locking;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Override;
use SensitiveParameter;

/**
 * The Eloquent user provider, registered under the `eloquent` driver name so every guard gets it.
 *
 * When the hashing parameters change, the framework re-hashes the password of a user who has just
 * proven it and saves the result. That write replaces a hash with another hash of the same secret:
 * it is maintenance, not an edit, and a lock on the account must not turn it into a failed login.
 *
 * The model cannot tell a rehash apart from a password change, because at save time it only sees two
 * hashes. The provider can: it only gets here after the credentials were validated, so this is the
 * one place allowed to step past the lock guard for the password column.
 */
final class LockAwareUserProvider extends EloquentUserProvider
{
    #[Override]
    public function rehashPasswordIfRequired(UserContract $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        Locked::withoutGuard(fn () => parent::rehashPasswordIfRequired($user, $credentials, $force));
    }
}
