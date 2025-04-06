<?php

namespace Modules\Core\Database\Seeders;

use Modules\Core\Models\User;
use Modules\Core\Models\License;
use Modules\Core\Helpers\BatchSeeder;

class DevCoreDatabaseSeeder extends BatchSeeder
{
    private const TARGET_COUNT_USERS = 1000;
    private const TARGET_COUNT_LICENSES = 200;

    protected function execute(): void
    {
        $this->seedUsers();
        $this->seedLicenses();
    }

    private function seedUsers(): void
    {
        $this->createInBatches(User::class, self::TARGET_COUNT_USERS);
    }

    private function seedLicenses(): void
    {
        $this->createInBatches(License::class, self::TARGET_COUNT_LICENSES);
    }
}
