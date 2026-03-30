<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Console\InitializeUsers;
use Override;

/**
 * Test double that bypasses interactive prompts while exercising the same seeding logic as InitializeUsers.
 */
final class InitializeUsersWithoutPrompts extends InitializeUsers
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
        return null;
    }
}
