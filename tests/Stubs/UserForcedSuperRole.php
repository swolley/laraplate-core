<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Database\Factories\UserFactory;
use Modules\Core\Models\User;

/**
 * Test double: reports superadmin via hasRole() while no matching Role row exists (observer edge case).
 */
final class UserForcedSuperRole extends User
{
    /**
     * Persist on the same table as User (subclass name would otherwise resolve to user_forced_super_roles).
     */
    protected $table = 'users';

    public function hasRole($roles, ?string $guard = null): bool
    {
        $superadmin_name = config('permission.roles.superadmin');

        if (is_string($roles) && $roles === $superadmin_name) {
            return true;
        }

        if (is_array($roles) && in_array($superadmin_name, $roles, true)) {
            return true;
        }

        return parent::hasRole($roles, $guard);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
