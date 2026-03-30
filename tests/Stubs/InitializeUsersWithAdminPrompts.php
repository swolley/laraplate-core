<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Console\InitializeUsers;
use Override;

/**
 * Test double that creates the admin user by returning fixed credentials from the optional admin prompt.
 */
final class InitializeUsersWithAdminPrompts extends InitializeUsers
{
    /**
     * @return array{email: string, password: string}
     */
    #[Override]
    protected function promptRootUserCredentials(): array
    {
        return ['email' => 'root@example.com', 'password' => 'secretpassword'];
    }

    /**
     * @return array{email: string, password: string}|null
     */
    #[Override]
    protected function promptOptionalAdminCredentials(): ?array
    {
        return ['email' => 'admin@example.com', 'password' => 'adminsecretpassword'];
    }
}
